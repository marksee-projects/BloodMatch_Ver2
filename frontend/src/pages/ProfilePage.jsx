import React, { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, User, Camera, CheckCircle, Drop, FileText, UploadSimple } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import PrivacyConsentModal from '../components/PrivacyConsentModal'
import LocationSelector from '../components/LocationSelector'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Badge, StatusPill } from '../components/ui/Badge'
import styles from './ProfilePage.module.css'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import { ProfileBanner } from '../components/ui/ProfileBanner'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
export default function ProfilePage() {
  const { id: profileId } = useParams()
  const isOtherProfile = Boolean(profileId)
  const { refresh } = useAuth()
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState(null)
  const [errorAlert, setErrorAlert] = useState(null)
  const [file, setFile] = useState(null)
  const [documentInputKey, setDocumentInputKey] = useState(0)
  const [documentUploading, setDocumentUploading] = useState(false)
  
  const [submitting, setSubmitting] = useState(false)
  const [enrolling, setEnrolling] = useState(false)
  const [idPrivacyAck, setIdPrivacyAck] = useState(false)
  const [idPrivacyModalOpen, setIdPrivacyModalOpen] = useState(false)
  const [idPrivacyError, setIdPrivacyError] = useState(null)
  const [pictureFile, setPictureFile] = useState(null)
  const [pictureInputKey, setPictureInputKey] = useState(0)
  const [pictureUploading, setPictureUploading] = useState(false)
  const [pictureFailed, setPictureFailed] = useState(false)
  const [location, setLocation] = useState({ location_id: null, municipality_code: null, barangay_code: null })
  const [activeTab, setActiveTab] = useState('overview')
  const navigate = useNavigate()

  const { data, isLoading, error: profileError, refetch } = useQuery({
    queryKey: isOtherProfile ? ['member-profile', profileId] : ['profile'],
    queryFn: async () => {
      const p = await api.get(isOtherProfile ? `/api/profile/${profileId}` : '/api/profile');
      let r = [];
      if (!isOtherProfile && p.profile.role === 'member') {
        const d = await api.get('/api/my/donation-reports');
        r = d.reports || [];
      }
      return { profile: p.profile, reports: r };
    },
    retry: false
  });

  const profile = data?.profile;
  const reports = data?.reports || [];

  useEffect(() => {
    if (!isOtherProfile && profile && !form) {
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
  }, [isOtherProfile, profile, form]);

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
      setErrorAlert('Select a National ID file to upload.')
      return
    }
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
    if (!allowedTypes.includes(file.type)) {
      setErrorAlert('National ID upload failed. Choose a JPG, PNG, WEBP, or PDF file.')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      setErrorAlert('National ID upload failed. The file must be 5 MB or smaller.')
      return
    }
    setDocumentUploading(true)
    try {
      const result = await api.upload('/api/profile/documents', file, { doc_type: 'national_id', privacy_acknowledged: '1' })
      setFile(null)
      setDocumentInputKey((value) => value + 1)
      setIdPrivacyAck(false)
      await load()
      setMessage(
        result?.resubmitted
          ? 'We received your new National ID. Your account is being reviewed. Please wait—you will be notified when a decision is made.'
          : 'National ID uploaded. Your account is being reviewed, and you will be notified when a decision is made.'
      )
    } catch (err) {
      setErrorAlert(err.message)
    } finally {
      setDocumentUploading(false)
    }
  }

  if (isLoading && !profile) {
    return (
      <Card padding="lg" style={{ textAlign: 'center', marginTop: 'var(--space-8)' }}>
        <LoadingSpinner text="Loading profile…" />
      </Card>
    )
  }

  if (isOtherProfile && profileError?.status === 404) {
    return (
      <div className={styles.container} style={{ padding: 'var(--space-4)' }}>
        <Card padding="lg" style={{ textAlign: 'center' }}>
          <h1>This profile isn&apos;t available</h1>
          <p className="muted">It may not exist, or you may not have an active request relationship that permits access.</p>
          <Button variant="secondary" onClick={() => navigate(-1)}>
            <ArrowLeft size={16} aria-hidden="true" /> Go Back
          </Button>
        </Card>
      </div>
    )
  }

  if (isOtherProfile && profileError) {
    return (
      <div className={styles.container} style={{ padding: 'var(--space-4)' }}>
        <Card padding="lg" style={{ textAlign: 'center' }}>
          <h1>Profile unavailable</h1>
          <p className="muted">{profileError.message}</p>
          <Button variant="secondary" onClick={() => navigate(-1)}><ArrowLeft size={16} aria-hidden="true" /> Go Back</Button>
        </Card>
      </div>
    )
  }

  if (isOtherProfile) {
    return (
      <div className={styles.container}>
        <div style={{ padding: 'var(--space-4) var(--space-4) 0' }}>
          <Button variant="secondary" size="sm" onClick={() => navigate(-1)}>
            <ArrowLeft size={16} aria-hidden="true" /> Back
          </Button>
        </div>
        <ProfileBanner profile={profile} navTabs={[{ id: 'overview', label: 'Overview' }]} />
        <div style={{ maxWidth: '800px', margin: 'var(--space-6) auto 0', padding: '0 var(--space-4)' }}>
          <Card>
            <div className={styles.cardHeader}>
              <h2 className={styles.cardTitle}>Member Profile</h2>
            </div>
            <dl className={styles.grid2}>
              <div><dt className={styles.metricLabel}>Email</dt><dd className={styles.metricValue}><a href={`mailto:${profile.email}`}>{profile.email}</a></dd></div>
              <div><dt className={styles.metricLabel}>Blood Type</dt><dd className={styles.metricValue}>{profile.blood_type || 'Not provided'}</dd></div>
              <div><dt className={styles.metricLabel}>Chapter</dt><dd className={styles.metricValue}>{profile.chapter_name || 'Not assigned'}</dd></div>
              <div><dt className={styles.metricLabel}>Role</dt><dd className={styles.metricValue}>{profile.role_label}</dd></div>
              <div><dt className={styles.metricLabel}>Member Since</dt><dd className={styles.metricValue}>{new Date(`${profile.member_since}T00:00:00`).toLocaleDateString()}</dd></div>
              <div><dt className={styles.metricLabel}>Verification</dt><dd className={styles.metricValue} style={{ textTransform: 'capitalize' }}>{profile.verification_status}</dd></div>
            </dl>
          </Card>
        </div>
      </div>
    )
  }

  if (!form) {
    return (
      <Card padding="lg" style={{ textAlign: 'center', marginTop: 'var(--space-8)' }}>
        <LoadingSpinner text="Loading profileâ€¦" />
      </Card>
    )
  }

  const nationalIdDocuments = (profile.documents || [])
    .filter((document) => document.doc_type === 'national_id')
    .sort((a, b) => b.id - a.id)

  return (
    <div className={styles.container}>
      {message && <StatusPill variant="active" style={{ width: '100%' }}>{message}</StatusPill>}
      {errorAlert && <StatusPill variant="error" style={{ width: '100%' }}>{errorAlert}</StatusPill>}

      {profile.verification_status === 'rejected' && (
        <div className={`${styles.alertBox} ${styles.alertDanger}`}>
          <p className={styles.alertDangerTitle}>Verification Needs Attention</p>
          <p className={styles.alertDangerText}>
            Your National ID could not be approved. Upload a clearer or valid National ID to request another review.
          </p>
          <Button variant="primary" onClick={() => setActiveTab('verification')}>
            Upload another National ID
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
              <div>
                <h3 className={styles.cardTitle}>National ID Verification</h3>
                <p className="muted" style={{ margin: 'var(--space-1) 0 0', fontSize: 'var(--text-sm)' }}>
                  A National ID is the required document for account verification.
                </p>
              </div>
              <Badge variant={profile.verification_status === 'verified' ? 'success' : profile.verification_status === 'rejected' ? 'urgent' : 'neutral'}>
                {profile.verification_status.replace('_', ' ').toUpperCase()}
              </Badge>
            </div>

            {profile.verification_status === 'pending' && nationalIdDocuments.length > 0 && (
              <div className="alert alert-success" role="status" style={{ marginBottom: 'var(--space-4)' }}>
                Your account is being processed. Please wait—you will receive a notification and email when a decision is made.
              </div>
            )}

            <div style={{ marginBottom: 'var(--space-4)' }}>
              {nationalIdDocuments.length === 0 ? (
                <div className="empty-state" style={{ padding: 'var(--space-5)' }}>
                  <FileText size={30} aria-hidden="true" />
                  <h3>No National ID uploaded</h3>
                  <p>Upload a clear National ID to request account verification.</p>
                </div>
              ) : (
                <div className={styles.tableContainer}>
                  <table className={styles.table}>
                    <thead>
                      <tr>
                        <th>Document</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {nationalIdDocuments.map((d, index) => (
                        <tr key={d.id}>
                          <td><strong>National ID</strong> {index === 0 && <Badge variant="neutral">Latest</Badge>}</td>
                          <td>{new Date(String(d.uploaded_at).replace(' ', 'T') + 'Z').toLocaleString()}</td>
                          <td>
                            <Button to={`/api/profile/documents/${d.id}/file`} target="_blank" rel="noreferrer" variant="secondary" size="sm">
                              View ID
                            </Button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {profile.role === 'member' && profile.verification_status !== 'verified' && (
              <form onSubmit={onUpload} style={{ borderTop: '1px solid var(--color-border-subtle)', paddingTop: 'var(--space-4)', marginTop: 'var(--space-4)', display: 'flex', flexDirection: 'column', gap: 'var(--space-4)' }}>
                <div className="field">
                  <label htmlFor="profile-national-id">{profile.verification_status === 'rejected' ? 'Upload another National ID' : 'Upload National ID'}</label>
                  <input
                    key={documentInputKey}
                    id="profile-national-id"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    onChange={(event) => setFile(event.target.files?.[0] || null)}
                  />
                  <small className="field-hint">JPG, PNG, WEBP, or PDF. Maximum file size: 5 MB.</small>
                  {file && <p className="muted" style={{ margin: 'var(--space-2) 0 0', fontSize: 'var(--text-sm)', overflowWrap: 'anywhere' }}>Selected: {file.name}</p>}
                </div>

                <div className="check-row">
                  <input
                    id="profile-id-privacy"
                    type="checkbox"
                    checked={idPrivacyAck}
                    onChange={(event) => {
                      setIdPrivacyAck(event.target.checked)
                      if (event.target.checked) setIdPrivacyError(null)
                    }}
                  />
                  <label htmlFor="profile-id-privacy">
                    I have read and acknowledge the Identification Document Privacy Notice.
                  </label>
                </div>
                {idPrivacyError && <span className="field-error" role="alert">{idPrivacyError}</span>}

                <div className="button-group">
                  <Button type="submit" isLoading={documentUploading} disabled={!file || !idPrivacyAck}>
                    <UploadSimple size={18} aria-hidden="true" />
                    {profile.verification_status === 'rejected' ? 'Upload ID and request another review' : 'Upload National ID'}
                  </Button>
                  <button type="button" className="btn btn-secondary" onClick={() => setIdPrivacyModalOpen(true)}>
                    Read privacy notice
                  </button>
                </div>
              </form>
            )}
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
      <PrivacyConsentModal 
        isOpen={idPrivacyModalOpen} 
        onClose={() => setIdPrivacyModalOpen(false)} 
        readonly
      />
      </div>
    </div>
  )
}
