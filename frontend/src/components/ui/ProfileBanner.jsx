import React, { useState } from 'react'
import { CheckCircle, Camera, User, Drop } from '@phosphor-icons/react'
import { Badge } from './Badge'
import styles from '../../pages/ProfilePage.module.css'

export function ProfileBanner({
  profile,
  isOwnProfile = false,
  onAvatarClick,
  reports = [],
  actionButton = null,
  activeTab = 'overview',
  onTabChange = null,
  navTabs = []
}) {
  const [pictureFailed, setPictureFailed] = useState(false);

  return (
    <div className={styles.profileHeader}>
      <div className={styles.profileHeaderContent} style={{ maxWidth: '100%' }}>
        <div className={styles.avatarWrapper}>
          {profile.profile_picture_url && !pictureFailed ? (
            <img
              className={styles.avatarLarge}
              src={profile.profile_picture_url}
              alt={`${profile.full_name}'s profile picture`}
              onError={() => setPictureFailed(true)}
              onClick={isOwnProfile ? onAvatarClick : undefined}
              style={{ cursor: isOwnProfile ? 'pointer' : 'default' }}
            />
          ) : (
            <span 
              className={styles.avatarLarge} 
              role="img" 
              aria-label="No profile picture uploaded"
              onClick={isOwnProfile ? onAvatarClick : undefined}
              style={{ cursor: isOwnProfile ? 'pointer' : 'default' }}
            >
              <User size={80} weight="regular" aria-hidden="true" />
            </span>
          )}
          
          {isOwnProfile && (
            <div 
              className={styles.avatarCameraOverlay} 
              title="Update profile picture"
              onClick={onAvatarClick}
            >
              <Camera size={20} weight="fill" />
            </div>
          )}
        </div>

        <div className={styles.profileHeaderInfo}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 'var(--space-4)', flexWrap: 'wrap' }}>
            <div>
              <h1 className={styles.profileNameLarge}>
                {profile.full_name}
                {profile.verification_status === 'verified' && (
                  <span className={styles.verifiedCheck} title="Verified Account">
                    <CheckCircle weight="fill" size={24} />
                  </span>
                )}
              </h1>
              <p className={styles.profileSubtitle}>
                {[
                  profile.chapter_name ? profile.chapter_name : (profile.chapter_id ? `Chapter #${profile.chapter_id}` : null),
                  profile.verification_status === 'verified' ? (profile.role || 'Member').charAt(0).toUpperCase() + (profile.role || 'Member').slice(1) : null
                ].filter(Boolean).join(' • ')}
              </p>

              {(profile.role === 'member' || profile.donor_availability) && (
                <p className={styles.profileBio}>
                  <Drop size={16} weight="fill" color="var(--color-brand-red)" />
                  {profile.availability_window?.blocked
                    ? `Resting until ${profile.availability_window.ends_at_utc.split(' ')[0]}`
                    : (profile.donor_availability === 'available' || profile.availability === 'available' 
                        ? 'Available to donate' 
                        : (profile.donor_availability === 'unavailable' || profile.availability === 'unavailable' ? 'Unavailable' : 'Donor Enrollment Inactive'))}
                  {reports.some(r => r.status === 'CONFIRMED') && ' • Experienced Donor'}
                </p>
              )}

              <div className={styles.profileTags}>
                {profile.account_status && profile.account_status !== 'active' && (
                  <Badge variant="neutral">{profile.account_status}</Badge>
                )}
                {profile.blood_type && (
                  <Badge variant="urgent">Blood Type: {profile.blood_type}</Badge>
                )}
              </div>
            </div>

            {actionButton && (
              <div style={{ marginLeft: 'auto' }}>
                {actionButton}
              </div>
            )}
          </div>
        </div>
      </div>

      {navTabs.length > 0 && (
        <div className={styles.headerNav}>
          {navTabs.map(tab => (
            <div 
              key={tab.id}
              className={`${styles.navTab} ${activeTab === tab.id ? styles.navTabActive : ''}`}
              onClick={() => onTabChange && onTabChange(tab.id)}
              style={onTabChange ? { cursor: 'pointer' } : { cursor: 'default' }}
            >
              {tab.label}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
