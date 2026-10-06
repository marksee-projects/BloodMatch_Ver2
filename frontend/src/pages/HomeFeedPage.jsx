import React from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CaretDown, MapPin } from '@phosphor-icons/react'
import { useAuth } from '../context/AuthContext'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { FeedCard } from '../components/FeedCard'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import DemandMapWidget from '../components/DemandMapWidget'
import EmailVerificationDialog from '../components/EmailVerificationDialog'
import { api } from '../services/apiClient'
import styles from './HomeFeedPage.module.css'

export default function HomeFeedPage() {
  const { user, refresh } = useAuth()
  const [verifyDialogOpen, setVerifyDialogOpen] = React.useState(false)
  const [demandExpanded, setDemandExpanded] = React.useState(false)
  const { data: feedData, isLoading: feedLoading, error: feedError, refetch: refetchFeed } = useQuery({
    queryKey: ['home-feed'],
    queryFn: () => api.get('/api/home-feed'),
    enabled: Boolean(user),
    retry: false
  })
  const requests = feedData?.requests || []
  const donorBloodType = feedData?.blood_type || user?.blood_type || null

  return (
    <div className={styles.layout}>
      {user && user.email_verified === false && (
        <div className={styles.unverifiedBanner}>
          <div className={styles.unverifiedContent}>
            <span className={styles.unverifiedText}>
              Please verify your email address to access all features.
            </span>
            <button className={styles.unverifiedButton} onClick={() => setVerifyDialogOpen(true)}>
              Verify Now
            </button>
          </div>
        </div>
      )}

      <section className={styles.feedLayout}>
        <aside className={styles.leftColumn}>
          <Card>
            <div className={styles.profileRow}>
              <div className={styles.profileAvatarWrapper}>
                {user?.profile_picture_url ? (
                  <img src={user.profile_picture_url} alt="Profile" className={styles.profileAvatar} />
                ) : (
                  <div className={styles.profileAvatar} />
                )}
                {user?.availability === 'available' && (
                  <div className={styles.statusIndicator} title="Available donor" />
                )}
              </div>
              <div className={styles.profileInfo}>
                <span className={styles.profileName}>{user?.full_name || 'User'}</span>
                <span className={styles.profileRole}>{user?.role || 'Member'}</span>
              </div>
            </div>

            <hr style={{ margin: 'var(--space-3) 0', border: 'none', borderTop: '1px solid var(--color-border-hairline)' }} />

            <details className={styles.quickLinksDetails}>
              <summary>Account Details</summary>
              <div style={{ marginTop: 'var(--space-3)' }}>
                <div className={styles.accountDetailRow}>
                  <span className={styles.accountDetailLabel}>Blood Type</span>
                  <span className={styles.accountDetailValue}>{user?.blood_type || 'Unknown'}</span>
                </div>
                <div className={styles.accountDetailRow}>
                  <span className={styles.accountDetailLabel}>Status</span>
                  <span className={styles.accountDetailValue}>{user?.verification_status === 'verified' ? 'Verified' : 'Pending Verification'}</span>
                </div>
                <div className={styles.accountDetailRow}>
                  <span className={styles.accountDetailLabel}>Chapter</span>
                  <span className={styles.accountDetailValue}>{user?.chapter_name || 'N/A'}</span>
                </div>
                <hr className={styles.accountDetailDivider} />
                <ul className={styles.quickLinks}>
                  <li><Link to="/requests/mine" className={styles.quickLink}>My Requests</Link></li>
                  <li><Link to="/profile" className={styles.quickLink}>Edit Profile</Link></li>
                </ul>
              </div>
            </details>
          </Card>
        </aside>

        <main className={styles.centerColumn}>
          <div className={styles.contextLine} aria-live="polite">
            {feedLoading ? 'Checking compatibility…' : `${requests.length} compatible ${requests.length === 1 ? 'request' : 'requests'}`}
          </div>

          {feedLoading && (
            <Card padding="md" className={styles.feedState}>
              <LoadingSpinner text="Loading matched requests…" minHeight="12rem" />
            </Card>
          )}

          {feedError && (
            <Card padding="md" className={styles.feedState}>
              <h2 className={styles.feedStateTitle}>Requests couldn&apos;t be loaded</h2>
              <p className={styles.feedStateText}>{feedError.message}</p>
              <Button variant="secondary" onClick={() => refetchFeed()}>Retry</Button>
            </Card>
          )}

          {!feedLoading && !feedError && requests.length === 0 && (
            <Card padding="md" className={styles.feedState}>
              <h2 className={styles.feedStateTitle}>{donorBloodType ? 'No compatible requests right now' : 'Blood type needed'}</h2>
              <p className={styles.feedStateText}>
                {donorBloodType
                  ? 'There are no open requests your blood type can support at the moment. Check again when new requests are published.'
                  : 'Update your profile with your blood type to build your compatibility feed.'}
              </p>
              {!donorBloodType && <Button variant="primary" to="/profile">Update profile</Button>}
            </Card>
          )}

          {!feedLoading && !feedError && requests.map((request) => (
            <FeedCard key={request.id} request={request} />
          ))}
        </main>

        <aside className={styles.rightColumn}>
          <Card padding="none" className={styles.demandCard}>
            <button
              type="button"
              className={styles.mapHeading}
              aria-expanded={demandExpanded}
              aria-controls="home-demand-content"
              onClick={() => setDemandExpanded((current) => !current)}
            >
              <MapPin size={20} weight="fill" aria-hidden="true" />
              <h2>Chapter Demand Map</h2>
              <CaretDown className={demandExpanded ? styles.mapCaretOpen : styles.mapCaret} size={18} aria-hidden="true" />
            </button>
            {demandExpanded && (
              <div id="home-demand-content" className={styles.demandContent}>
                <DemandMapWidget mode="mini" />
              </div>
            )}
          </Card>
        </aside>
      </section>

      <EmailVerificationDialog
        isOpen={verifyDialogOpen}
        initialEmail={user?.email}
        onClose={() => setVerifyDialogOpen(false)}
        onSuccess={() => {
          setVerifyDialogOpen(false)
          refresh()
        }}
      />
    </div>
  )
}
