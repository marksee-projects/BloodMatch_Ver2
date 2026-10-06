import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { MapPin, CaretDown, CaretUp, Clock, MapPinLine, Drop, User, CheckCircle } from '@phosphor-icons/react';
import { Card } from './ui/Card';
import { Button } from './ui/Button';
import { BloodTypeBlock } from './ui/BloodTypeBlock';
import styles from './FeedCard.module.css';

export function FeedCard({ request, expandedByDefault = false, hideActions = false, variant = 'card' }) {
  const [isExpanded, setIsExpanded] = useState(expandedByDefault);

  const urgencyColors = {
    critical: 'var(--color-critical, #e53e3e)',
    urgent: 'var(--color-warning, #dd6b20)',
    routine: 'var(--color-success-green, #38a169)',
  };

  if (variant === 'post') {
    return (
      <div className={styles.feedCardPost}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)', marginBottom: 'var(--space-4)' }}>
          <div style={{ width: '48px', height: '48px', borderRadius: '50%', overflow: 'hidden', backgroundColor: 'var(--color-neutral-200)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
            {request.requester_profile_picture_url ? (
              <img src={request.requester_profile_picture_url} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            ) : (
              <User size={24} weight="regular" color="var(--color-neutral-500)" />
            )}
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
              <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 700 }}>
                {request.requester_name || 'Anonymous User'}
              </h3>
              {request.requester_verification_status === 'verified' && (
                <CheckCircle weight="fill" size={18} color="var(--color-brand-blue)" title="Verified Account" />
              )}
            </div>
            <div style={{ fontSize: '0.9rem', color: 'var(--color-text-muted)', fontWeight: 500, marginTop: '2px' }}>
              {request.requester_verification_status === 'verified' && request.requester_chapter_name ? request.requester_chapter_name : 'Community Member'} • {new Date(request.needed_datetime.replace(' ', 'T') + 'Z').toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
            </div>
          </div>
          <div>
            <BloodTypeBlock bloodType={request.required_blood_type} level={request.urgency} />
          </div>
        </div>

        {request.description && (
          <div style={{ 
            fontSize: '1rem', 
            lineHeight: 1.6, 
            color: 'var(--color-text)', 
            whiteSpace: 'pre-wrap', 
            marginBottom: 'var(--space-5)',
            padding: 'var(--space-4)',
            backgroundColor: 'var(--color-surface-sunken)',
            borderRadius: 'var(--radius-md)',
            overflowWrap: 'anywhere',
            wordBreak: 'break-word'
          }}>
            <div style={{ fontSize: 'var(--text-xs)', textTransform: 'uppercase', color: 'var(--color-text-muted)', fontWeight: 600, marginBottom: 'var(--space-2)', letterSpacing: '0.05em' }}>Situation Details</div>
            {request.description}
          </div>
        )}

        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--space-4)', padding: 'var(--space-4)', backgroundColor: 'var(--color-surface-sunken)', borderRadius: 'var(--radius-md)', border: '1px solid var(--color-border-subtle)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', fontSize: '0.95rem', fontWeight: 600 }}>
            <Drop size={18} weight="fill" color={urgencyColors[request.urgency] || 'var(--color-brand-red)'} />
            {request.quantity_units} Unit(s) Needed
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', fontSize: '0.95rem', color: 'var(--color-text-muted)', fontWeight: 500 }}>
            <MapPin size={18} />
            {request.facility_name}{request.location?.municipality_name ? `, ${request.location.municipality_name}` : ''}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', fontSize: '0.95rem', color: urgencyColors[request.urgency] || 'inherit', fontWeight: 700, textTransform: 'capitalize' }}>
            <Clock size={18} />
            {request.urgency}
          </div>
        </div>
        {request.can_view_requester_profile && request.requester_id && (
          <div style={{ marginTop: 'var(--space-3)' }}>
            <Button variant="secondary" size="sm" to={`/profile/${request.requester_id}`}>
              View Profile
            </Button>
          </div>
        )}
      </div>
    );
  }

  return (
    <Card padding="none" className={styles.feedCard}>
      <div 
        className={styles.feedCardHeader} 
        onClick={() => setIsExpanded(!isExpanded)}
        role="button"
        tabIndex={0}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)' }}>
          <div style={{ width: '40px', height: '40px', borderRadius: '50%', overflow: 'hidden', backgroundColor: 'var(--color-neutral-200)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
            {request.requester_profile_picture_url ? (
              <img src={request.requester_profile_picture_url} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            ) : (
              <User size={20} weight="regular" color="var(--color-neutral-500)" />
            )}
          </div>
          <div className={styles.feedCardHeaderInfo}>
            <h3 className={styles.feedCardTitle} style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
              {request.requester_name || 'Anonymous User'}
              {request.requester_verification_status === 'verified' && (
                <CheckCircle weight="fill" size={16} color="var(--color-brand-blue)" title="Verified Account" />
              )}
            </h3>
            <p className={styles.feedCardMeta}>
              {request.requester_verification_status === 'verified' && request.requester_chapter_name ? request.requester_chapter_name : 'Community Member'}
            </p>
          </div>
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 'var(--space-3)' }}>
          <BloodTypeBlock bloodType={request.required_blood_type} level={request.urgency} />
        </div>
        <div className={styles.expandIconWrapper}>
          {isExpanded ? <CaretUp size={16} /> : <CaretDown size={16} />}
        </div>
      </div>
      

      {isExpanded && (
        <div className={styles.feedCardDetails}>
          <div className={styles.detailsGrid}>
            <div className={styles.detailItem}>
              <span className={styles.detailLabel}>Units Needed</span>
              <span className={styles.detailValue}>
                <Drop size={16} weight="fill" color={urgencyColors[request.urgency] || 'var(--color-brand-red)'} />
                {request.quantity_units} Unit(s)
              </span>
            </div>
            <div className={styles.detailItem}>
              <span className={styles.detailLabel}>Hospital / Clinic</span>
              <span className={styles.detailValue}>
                <MapPin size={16} color="var(--color-text-muted)" />
                {request.facility_name}
              </span>
            </div>
            {request.location?.municipality_name && (
              <div className={styles.detailItem}>
                <span className={styles.detailLabel}>Municipality</span>
                <span className={styles.detailValue}>
                  <MapPinLine size={16} color="var(--color-text-muted)" />
                  {request.location.municipality_name}
                </span>
              </div>
            )}
            <div className={styles.detailItem}>
              <span className={styles.detailLabel}>Needed By</span>
              <span className={styles.detailValue}>
                <Clock size={16} color="var(--color-text-muted)" />
                {new Date(request.needed_datetime.replace(' ', 'T') + 'Z').toLocaleString(undefined, {
                  month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                })}
              </span>
            </div>
            <div className={styles.detailItem}>
              <span className={styles.detailLabel}>Urgency</span>
              <span className={styles.detailValue} style={{ textTransform: 'capitalize', color: urgencyColors[request.urgency] || 'inherit', fontWeight: 700 }}>
                {request.urgency}
              </span>
            </div>
          </div>
          
          {!hideActions && (
            <div className={styles.feedCardActions} style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
              <Button variant="primary" size="sm" to={`/requests/${request.id}/matches`}>
                View Details & Respond
              </Button>
              {request.can_view_requester_profile && request.requester_id && (
                <Button variant="secondary" size="sm" to={`/profile/${request.requester_id}`} style={{ marginLeft: 'auto' }}>
                  View Profile
                </Button>
              )}
            </div>
          )}
        </div>
      )}
    </Card>
  );
}
