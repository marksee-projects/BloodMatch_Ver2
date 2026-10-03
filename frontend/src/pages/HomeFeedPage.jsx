import React from 'react';
import { Link } from 'react-router-dom';
import { MapPin, CaretDown } from '@phosphor-icons/react';
import { useAuth } from '../context/AuthContext';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { BloodTypeBlock } from '../components/ui/BloodTypeBlock';
import DemandMapWidget from '../components/DemandMapWidget';
import styles from './HomeFeedPage.module.css';

export default function HomeFeedPage() {
  const { user } = useAuth();

  return (
    <div className={styles.layout}>
      
      {/* Feed Layout */}
      <section className={styles.feedLayout}>
        
        {/* Left Column: Compact Profile Card */}
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

        {/* Center Column: Feed */}
        <main className={styles.centerColumn}>

          <div className={styles.contextLine}>
            Recent regional blood requests
          </div>

          {/* Placeholder Feed Cards (compact, not stretched) */}
          <Card padding="md" className={styles.feedCard}>
            <div className={styles.feedCardInner}>
              <BloodTypeBlock bloodType="O+" level="critical" />
              <div style={{ flex: 1 }}>
                <h3 className={styles.feedCardTitle}>Patient needs O+ Blood</h3>
                <p className={styles.feedCardMeta}>{user?.chapter_name || 'Mt. Samat Chapter'} · 15 mins ago</p>
              </div>
              <Button variant="secondary" size="sm" to={`/requests/mine`}>
                View
              </Button>
            </div>
          </Card>

          <Card padding="md" className={styles.feedCard}>
            <div className={styles.feedCardInner}>
              <BloodTypeBlock bloodType="A-" level="urgent" />
              <div style={{ flex: 1 }}>
                <h3 className={styles.feedCardTitle}>Patient needs A- Blood</h3>
                <p className={styles.feedCardMeta}>{user?.chapter_name || 'Mt. Tarak Chapter'} · 2 hrs ago</p>
              </div>
              <Button variant="secondary" size="sm" to={`/requests/mine`}>
                View
              </Button>
            </div>
          </Card>

        </main>

        {/* Right Column: Demand Map Mini */}
        <aside className={styles.rightColumn}>
          <Card padding="none">
            <details className={styles.demandMapDetails} open={false}>
              <summary className={styles.demandMapSummary}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
                  <MapPin size={20} weight="fill" color="var(--color-brand-navy)" />
                  <span>Regional Demand Map</span>
                </div>
                <CaretDown size={16} className={styles.summaryChevron} color="var(--color-text-subtle)" />
              </summary>
              <div style={{ padding: '0 var(--space-4) var(--space-4) var(--space-4)' }}>
                <DemandMapWidget mode="mini" />
              </div>
            </details>
          </Card>
        </aside>

      </section>
    </div>
  );
}
