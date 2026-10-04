import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, MapPinLine } from '@phosphor-icons/react';
import { api } from '../services/apiClient';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { Card } from '../components/ui/Card';
import { ProfileBanner } from '../components/ui/ProfileBanner';
import styles from './ProfilePage.module.css';

export default function PublicProfilePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    api.get(`/api/users/${id}`)
      .then(data => setProfile(data.profile))
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) {
    return (
      <Card padding="lg" style={{ textAlign: 'center', marginTop: 'var(--space-8)' }}>
        <LoadingSpinner text="Loading profile..." />
      </Card>
    );
  }

  if (error || !profile) {
    return (
      <div className={styles.container} style={{ padding: 'var(--space-4)' }}>
        <div className="alert alert-error" role="alert">{error || 'User not found.'}</div>
        <button className="btn btn-secondary" onClick={() => navigate(-1)} style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
          <ArrowLeft size={14} /> Go Back
        </button>
      </div>
    );
  }

  return (
    <div className={styles.container}>
      <div style={{ padding: 'var(--space-4) var(--space-4) 0' }}>
        <button type="button" onClick={() => navigate(-1)} className="btn btn-secondary btn-sm" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
          <ArrowLeft size={14} /> Back
        </button>
      </div>

      <ProfileBanner 
        profile={profile}
        activeTab="overview"
        navTabs={[{ id: 'overview', label: 'Overview' }]}
      />
      
      <div style={{ maxWidth: '800px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 'var(--space-6)', padding: '0 var(--space-4)', marginTop: 'var(--space-6)' }}>
        <Card>
          <div className={styles.cardHeader}>
            <h3 className={styles.cardTitle}>Personal & Location</h3>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 'var(--space-4)', marginTop: 'var(--space-2)' }}>
            <div>
              <span className={styles.metricLabel}>Email</span>
              <p className={styles.metricValue} style={{ textTransform: 'none' }}>
                <a href={`mailto:${profile.email}`} style={{ color: 'var(--color-brand-blue)', textDecoration: 'none' }}>
                  {profile.email || 'Hidden'}
                </a>
              </p>
            </div>
            <div>
              <span className={styles.metricLabel}>Blood Type</span>
              <p className={styles.metricValue}>{profile.blood_type || 'Unknown'}</p>
            </div>
            <div>
              <span className={styles.metricLabel}>Location</span>
              <p className={styles.metricValue}>
                <MapPinLine size={16} style={{ verticalAlign: 'middle', marginRight: '4px' }} />
                {profile.chapter_name ? profile.chapter_name : (profile.chapter_id ? `Chapter #${profile.chapter_id}` : 'Unspecified')}
              </p>
            </div>
            <div>
              <span className={styles.metricLabel}>Verification Status</span>
              <p className={styles.metricValue} style={{ textTransform: 'capitalize' }}>
                {profile.verification_status}
              </p>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
