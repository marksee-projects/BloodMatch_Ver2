import React, { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { ShieldCheck, User, Camera, CheckCircle, Drop } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import PrivacyNoticeModal, {
  ID_PRIVACY_CHECKBOX_LABEL,
  ID_PRIVACY_TITLE,
  IdPrivacyBody
} from '../components/PrivacyNoticeModal'
import LocationSelector from '../components/LocationSelector'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Badge, StatusPill } from '../components/ui/Badge'
import styles from './ProfilePage.module.css'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const DOC_TYPES = [
  { id: 'national_id', label: 'Government-Issued ID (National ID, Passport, Driver\'s License)' },
  { id: 'donor_card', label: 'Official Blood Donor Card (Philippine Red Cross / DOH)' },
  { id: 'parental_consent', label: 'Parental / Guardian Consent Form (Ages 16–17)' }
]

export default function ProfilePage() {
  const { refresh } = useAuth()
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState(null)
  const [errorAlert, setErrorAlert] = useState(null)
  const [docType, setDocType] = useState('national_id')
  const [file, setFile] = useState(null)
  
  const [submitting, setSubmitting] = useState(false)
  const [enrolling, setEnrolling] = useState(false)
  const [idPrivacyAck, setIdPrivacyAck] = useState(true)
  const [idPrivacyModalOpen, setIdPrivacyModalOpen] = useState(false)
  const [idPrivacyError, setIdPrivacyError] = useState(null)
  const [pictureFile, setPictureFile] = useState(null)
  const [pictureInputKey, setPictureInputKey] = useState(0)
  const [pictureUploading, setPictureUploading] = useState(false)
  const [pictureFailed, setPictureFailed] = useState(false)
  const [location, setLocation] = useState({ location_id: null, municipality_code: null, barangay_code: null })
  const [activeTab, setActiveTab] = useState('overview')
  const navigate = useNavigate()

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['profile'],
    queryFn: async () => {
      const p = await api.get('/api/profile');
      let r = [];
      if (p.profile.role === 'member') {
        const d = await api.get('/api/my/donation-reports');
        r = d.reports || [];
      }
      return { profile: p.profile, reports: r };
    }
  });

  const profile = data?.profile;
  const reports = data?.reports || [];

  useEffect(() => {
    if (profile && !form) {
      setForm({
        full_name: profile.full_name,
        phone: profile.phone || '',
        date_of_birth: profile.date_of_birth || '',
        blood_type: profile.blood_type || ''
      });
      setLocation({
        location_id: profile.location?.location_id ?? null,
        municipality_code: profile.location?.municipality_code ?? null,
        barangay_code: profile.location?.barangay_code ?? null
      });
    }
  }, [profile]);

  const load = () => refetch();

  const setAvailability = async (value) => {
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post('/api/profile/donor-availability', { availability: value })
      await load()
      setMessage(`Donor availability updated to ${value.toUpperCase()}.`)
    } catch (err) {
      setErrorAlert(err.message)
    }
  }

  const enrollAsDonor = async () => {
    setEnrolling(true)
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post('/api/profile/enroll-donor')
      await load()
      setMessage('Successfully enrolled as a volunteer blood donor! You will now be matched when compatible requests arise in your area.')
    } catch (err) {
      setErrorAlert(err.message)
    } finally {
      setEnrolling(false)
    }
  }



  useEffect(() => {
    setPictureFailed(false)
  }, [profile?.profile_picture_url])

  const onPictureUpload = async (fileToUpload) => {
    setMessage(null)
    setErrorAlert(null)
    if (!fileToUpload) {
      setErrorAlert('Please select an image to upload.')
      return
    }
    setPictureUploading(true)
    try {
      await api.upload('/api/profile/picture', fileToUpload)
      setPictureFile(null)
      setPictureInputKey((k) => k + 1)
      await load()
      await refresh()
      setMessage('Profile picture updated successfully.')
    } catch (err) {
      setErrorAlert(err.message)
    } finally {
      setPictureUploading(false)
    }
  }

  const setField = (name) => (e) => setForm((f) => ({ ...f, [name]: e.target.value }))

  const onSave = async (e) => {
    e.preventDefault()
    setErrors({})
    setMessage(null)
    setErrorAlert(null)
    if (!location.location_id) {
      setErrors({ location_id: ['Please select your municipality or city.'] })
      return
    }
    setSubmitting(true)
    try {
      const data = await api.put('/api/profile', {
        full_name: form.full_name,
        phone: form.phone || null,
        date_of_birth: form.date_of_birth || null,
        blood_type: form.blood_type || null,
        location_id: location.location_id
      })
      refetch()
      setMessage('Profile details saved successfully.')
    } catch (err) {
      if (err.details && Object.keys(err.details).length > 0) {
        setErrors(err.details)
      } else {
        setErrorAlert(err.message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  const onUpload = async (e) => {
    e.preventDefault()
    setMessage(null)
    setErrorAlert(null)
    if (!idPrivacyAck) {
      setIdPrivacyError('Please read the Identification Document Privacy Notice and check the acknowledgment before uploading.')
      return
    }
    setIdPrivacyError(null)
    if (!file) {
      setErrorAlert('Please select a file to upload.')
      return
    }
    try {
      await api.upload('/api/profile/documents', file, { doc_type: docType, privacy_acknowledged: '1' })
      setFile(null)
      setIdPrivacyAck(false)
      await load()
      setMessage('Document uploaded successfully.')
    } catch (err) {
      setErrorAlert(err.message)
    }
  }

  const onResubmit = async () => {
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post('/api/profile/resubmit')
      await load()
      setMessage('Verification resubmitted. An officer will review your updated documents.')
    } catch (err) {
      setErrorAlert(err.message)
    }
  }

  if ((isLoading && !profile) || !form) {
    return (
      <Card padding="lg" style={{ textAlign: 'center', marginTop: 'var(--space-8)' }}>
        <LoadingSpinner text="Loading profile…" />
      </Card>
    )
  }

  return (
    <div className={styles.container}>
      {message && <StatusPill variant="active" style={{ width: '100%' }}>{message}</StatusPill>}
      {errorAlert && <StatusPill variant="error" style={{ width: '100%' }}>{errorAlert}</StatusPill>}

      {profile.verification_status === 'rejected' && (
        <div className={`${styles.alertBox} ${styles.alertDanger}`}>
          <p className={styles.alertDangerTitle}>Verification Needs Attention</p>
          <p className={styles.alertDangerText}>
            Your verification was rejected by a chapter officer. Please review and upload clear identification or donor documents below, then resubmit for review.
          </p>
          <Button variant="primary" onClick={onResubmit}>
            Resubmit for Verification
          </Button>
        </div>
      )}

      <div className={styles.profileHeader}>
        <div className={styles.profileHeaderContent} style={{ maxWidth: '100%' }}>
          <div className={styles.avatarWrapper}>
            {profile.profile_picture_url && !pictureFailed ? (
              <img
                className={styles.avatarLarge}
                src={profile.profile_picture_url}
                alt={`${profile.full_name}'s profile picture`}
                onError={() => setPictureFailed(true)}
                onClick={() => document.getElementById('hidden_profile_picture_file').click()}
              />
            ) : (
              <span 
                className={styles.avatarLarge} 
                role="img" 
                aria-label="No profile picture uploaded"
                onClick={() => document.getElementById('hidden_profile_picture_file').click()}
              >
                <User size={80} weight="regular" aria-hidden="true" />
              </span>
            )}
            
            <div 
              className={styles.avatarCameraOverlay} 
              title="Update profile picture"
              onClick={() => document.getElementById('hidden_profile_picture_file').click()}
            >
              <Camera size={20} weight="fill" />
            </div>

            <input
              id="hidden_profile_picture_file"
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              style={{ display: 'none' }}
              onChange={(e) => {
                const f = e.target.files[0]
                if (f) {
                  setPictureFile(f)
                  onPictureUpload(f)
                }
              }}
            />
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
                  {profile.chapter_name ? `${profile.chapter_name} • ` : ''}
                  {profile.role.charAt(0).toUpperCase() + profile.role.slice(1)}
                </p>

                {profile.role === 'member' && (
                  <p className={styles.profileBio}>
                    <Drop size={16} weight="fill" color="var(--color-brand-red)" />
                    {profile.availability_window?.blocked
                      ? `Resting until ${profile.availability_window.ends_at_utc.split(' ')[0]}`
                      : (profile.availability === 'available' ? 'Available to donate' : 'Donor Enrollment Inactive')}
                    {reports.some(r => r.status === 'CONFIRMED') && ' • Experienced Donor'}
                  </p>
                )}

                <div className={styles.profileTags}>
                  {profile.account_status !== 'active' && (
                    <Badge variant="neutral">{profile.account_status}</Badge>
                  )}
                  {profile.blood_type && (
                    <Badge variant="urgent">Blood Type: {profile.blood_type}</Badge>
                  )}
                </div>
              </div>

              <div style={{ marginLeft: 'auto' }}>
                <Button onClick={() => navigate('/requests/new')} variant="primary">
                  Request Blood
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div className={styles.headerNav}>
          <div 
            className={`${styles.navTab} ${activeTab === 'overview' ? styles.navTabActive : ''}`}
            onClick={() => setActiveTab('overview')}
          >
            Overview
          </div>
          <div 
            className={`${styles.navTab} ${activeTab === 'verification' ? styles.navTabActive : ''}`}
            onClick={() => setActiveTab('verification')}
          >
            Verification
          </div>
          {profile.role === 'member' && (
            <div 
              className={`${styles.navTab} ${activeTab === 'donations' ? styles.navTabActive : ''}`}
              onClick={() => setActiveTab('donations')}
            >
              Donations
            </div>
          )}
        </div>
      </div>

      <div style={{ maxWidth: '800px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: 'var(--space-6)', padding: '0 var(--space-4)' }}>
        
        {activeTab === 'overview' && (
          <Card>
            <div className={styles.cardHeader}>
              <h3 className={styles.cardTitle}>Personal & Location</h3>
            </div>

            <form onSubmit={onSave} noValidate style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-4)' }}>
              <Input 
                label="Full Name"
                id="full_name" 
                value={form.full_name} 
                onChange={setField('full_name')} 
                required 
                maxLength={150} 
                error={errors.full_name?.join(' ')}
              />

              <div className={styles.grid2}>
                <Input 
                  label="Phone Number"
                  id="phone" 
                  type="tel" 
                  value={form.phone} 
                  onChange={setField('phone')} 
                  placeholder="0917-123-4567" 
                  error={errors.phone?.join(' ')}
                />

                <Input 
                  label="Date of Birth"
                  id="dob" 
                  type="date" 
                  value={form.date_of_birth} 
                  onChange={setField('date_of_birth')} 
                  error={errors.date_of_birth?.join(' ')}
                />
              </div>

              <div>
                <label htmlFor="blood_type" style={{ fontSize: 'var(--text-sm)', fontWeight: '500', color: 'var(--color-text-secondary)', display: 'block', marginBottom: 'var(--space-2)' }}>Blood Type</label>
                <select id="blood_type" value={form.blood_type} onChange={setField('blood_type')} style={{ width: '100%', height: '44px', padding: '0 var(--space-4)', borderRadius: 'var(--radius-control)', border: '1px solid var(--color-border-strong)', backgroundColor: 'var(--color-surface)', color: 'var(--color-text)', fontFamily: 'var(--font-sans)', outline: 'none' }}>
                  <option value="">Unknown / Not sure</option>
                  {BLOOD_TYPES.map((t) => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
                {errors.blood_type && <span style={{ fontSize: 'var(--text-xs)', color: 'var(--color-critical-red)' }}>{errors.blood_type.join(' ')}</span>}
              </div>

              <div role="group" aria-labelledby="profile-location-heading">
                <span id="profile-location-heading" className={styles.metricLabel}>Location in Bataan</span>
                <LocationSelector
                  municipalityId="profile-municipality"
                  municipalityCode={location.municipality_code}
                  barangayCode={location.barangay_code}
                  onChange={setLocation}
                  errors={errors}
                />
              </div>

              <Button type="submit" isLoading={submitting} style={{ alignSelf: 'flex-start' }}>
                Save Changes
              </Button>
            </form>
          </Card>
        )}

        {activeTab === 'verification' && (
          <Card>
            <div className={styles.cardHeader}>
              <h3 className={styles.cardTitle}>Verification Documents</h3>
            </div>
            
            <div style={{ marginBottom: 'var(--space-4)' }}>
              {profile.documents.length === 0 ? (
                <p style={{ color: 'var(--color-text-muted)', fontSize: '0.875rem' }}>No verification documents uploaded yet. Upload a valid ID during registration to get verified.</p>
              ) : (
                <div className={styles.tableContainer}>
                  <table className={styles.table}>
                    <thead>
                      <tr>
                        <th>Document Type</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {profile.documents.map((d) => (
                        <tr key={d.id}>
                          <td><strong>{d.doc_type.replace('_', ' ').toUpperCase()}</strong></td>
                          <td>
                            <Button to={`/api/profile/documents/${d.id}/file`} target="_blank" rel="noreferrer" variant="secondary" size="sm">
                              View
                            </Button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div style={{ borderTop: '1px solid var(--color-border-subtle)', paddingTop: 'var(--space-4)', marginTop: 'var(--space-4)' }}>
              <p style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-muted)', display: 'flex', alignItems: 'center', gap: '4px', margin: 0 }}>
                <ShieldCheck size={14} weight="fill" color="var(--color-brand-blue)" />
                You have agreed to the ID Privacy Policy. 
                <button
                  type="button"
                  style={{ background: 'none', border: 'none', color: 'var(--color-brand-blue)', cursor: 'pointer', textDecoration: 'underline', padding: 0, fontWeight: 500, fontSize: 'var(--text-xs)' }}
                  onClick={(e) => {
                    e.preventDefault();
                    setIdPrivacyModalOpen(true);
                  }}
                >
                  Read full privacy notice
                </button>
              </p>
            </div>
          </Card>
        )}

        {activeTab === 'donations' && profile.role === 'member' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-6)' }}>
            <Card>
              <div className={styles.cardHeader}>
                <h3 className={styles.cardTitle}>Volunteer Enrollment</h3>
                {profile.donor_enrolled ? (
                  <Badge variant={profile.availability_window?.blocked ? 'urgent' : (profile.availability === 'available' ? 'success' : 'neutral')}>
                    {profile.availability_window?.blocked
                      ? profile.availability_window.which.toUpperCase()
                      : (profile.availability ? profile.availability.toUpperCase() : 'ENROLLED')}
                  </Badge>
                ) : (
                  <Badge variant="neutral">NOT ENROLLED</Badge>
                )}
              </div>

              {profile.verification_status !== 'verified' ? (
                <p style={{ color: 'var(--color-text-muted)', fontSize: '0.875rem' }}>
                  Donor enrollment requires verified member status. Once verified, you can opt in to receive compatibility match notifications.
                </p>
              ) : !profile.donor_enrolled ? (
                <div>
                  <p style={{ color: 'var(--color-text-muted)', fontSize: '0.875rem', marginBottom: 'var(--space-4)' }}>
                    Enroll as a volunteer blood donor. When local patients create blood requests matching your blood type, you will receive automated notifications.
                  </p>
                  <Button onClick={enrollAsDonor} isLoading={enrolling}>
                    Enroll as Volunteer Donor
                  </Button>
                </div>
              ) : (
                <div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)', marginBottom: 'var(--space-4)' }}>
                    <div>
                      <span className={styles.metricLabel}>Current Status</span>
                      <p className={styles.metricValue}>
                        {profile.availability_window?.blocked
                          ? `Locked until ${profile.availability_window.ends_at_utc} UTC`
                          : (profile.availability === 'available' ? 'Available to receive match alerts' : 'Unavailable (Temporarily paused)')}
                      </p>
                    </div>

                    {!profile.availability_window?.blocked && (
                      <div className={styles.buttonGroup}>
                        <Button
                          variant={profile.availability === 'available' ? 'primary' : 'secondary'}
                          disabled={profile.availability === 'available'}
                          onClick={() => setAvailability('available')}
                          size="sm"
                        >
                          Set Available
                        </Button>
                        <Button
                          variant={profile.availability === 'unavailable' ? 'primary' : 'secondary'}
                          disabled={profile.availability === 'unavailable'}
                          onClick={() => setAvailability('unavailable')}
                          size="sm"
                        >
                          Set Unavailable
                        </Button>
                      </div>
                    )}
                  </div>

                  {profile.availability_window?.blocked && (
                    <div className={`${styles.alertBox} ${styles.alertInfo}`} style={{ marginBottom: 0 }}>
                      <strong>Post-Donation Active:</strong> Changes to availability are locked.
                    </div>
                  )}
                </div>
              )}
            </Card>




      {/* Section 5: Donation History */}
      <Card>
        <div className={styles.cardHeader}>
          <h3 className={styles.cardTitle}>My Donation History</h3>
        </div>

        {reports.length === 0 ? (
          <p style={{ color: 'var(--color-text-muted)', fontSize: '0.875rem', margin: 0 }}>
            No completed donation reports recorded yet.
          </p>
        ) : (
          <div className={styles.tableContainer}>
            <table className={styles.table}>
              <thead>
                <tr>
                  <th>Report ID</th>
                  <th>Blood Type</th>
                  <th>Facility</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {reports.map((rep) => (
                  <tr key={rep.id}>
                    <td><code>#{rep.id}</code></td>
                    <td><strong>{rep.required_blood_type}</strong></td>
                    <td>{rep.facility_name}</td>
                    <td>
                      <Badge variant={rep.status === 'CONFIRMED' ? 'success' : (rep.status === 'REJECTED' ? 'critical' : 'urgent')}>
                        {rep.status}
                      </Badge>
                    </td>
                    <td>{new Date(rep.reported_at.replace(' ', 'T') + 'Z').toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      </div>
      )}
      <PrivacyNoticeModal
        open={idPrivacyModalOpen}
        title={ID_PRIVACY_TITLE}
        checkboxLabel={ID_PRIVACY_CHECKBOX_LABEL}
        checkboxId="id-privacy-ack-modal"
        acknowledged={idPrivacyAck}
        onAcknowledgeChange={(v) => {
          setIdPrivacyAck(v)
          if (v) setIdPrivacyError(null)
        }}
        onClose={() => setIdPrivacyModalOpen(false)}
        onConfirm={() => setIdPrivacyModalOpen(false)}
        confirmLabel="Continue"
        Body={IdPrivacyBody}
      />
      </div>
    </div>
  )
}
