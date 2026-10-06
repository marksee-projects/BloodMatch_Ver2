import React, { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Camera, CheckCircle, Drop, FileText, UploadSimple, User } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import PrivacyConsentModal from '../components/PrivacyConsentModal'
import LocationSelector from '../components/LocationSelector'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Badge, StatusPill } from '../components/ui/Badge'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import styles from './ProfilePage.module.css'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

function formatDate(value, fallback = 'Not provided') {
  if (!value) return fallback
  return new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`).toLocaleDateString()
}

function titleCase(value) {
  if (!value) return 'Not provided'
  return value.charAt(0).toUpperCase() + value.slice(1).toLowerCase()
}

export default function ProfilePage() {
  const { id: profileId } = useParams()
  const isOtherProfile = Boolean(profileId)
  const { refresh } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState(null)
  const [location, setLocation] = useState({ location_id: null, municipality_code: null, barangay_code: null })
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState(null)
  const [errorAlert, setErrorAlert] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [enrolling, setEnrolling] = useState(false)
  const [availabilityUpdating, setAvailabilityUpdating] = useState(false)
  const [file, setFile] = useState(null)
  const [documentInputKey, setDocumentInputKey] = useState(0)
  const [documentUploading, setDocumentUploading] = useState(false)
  const [idPrivacyModalOpen, setIdPrivacyModalOpen] = useState(false)
  const [pictureInputKey, setPictureInputKey] = useState(0)
  const [pictureUploading, setPictureUploading] = useState(false)
  const [pictureFailed, setPictureFailed] = useState(false)

  const { data, isLoading, error: profileError, refetch } = useQuery({
    queryKey: isOtherProfile ? ['member-profile', profileId] : ['profile'],
    queryFn: async () => {
      const response = await api.get(isOtherProfile ? `/api/profile/${profileId}` : '/api/profile')
      if (isOtherProfile || response.profile.role !== 'member') {
        return { profile: response.profile, reports: [], requests: [] }
      }
      const [donations, requests] = await Promise.all([
        api.get('/api/my/donation-reports'),
        api.get('/api/my/requests')
      ])
      return {
        profile: response.profile,
        reports: donations.reports || [],
        requests: requests.requests || []
      }
    },
    retry: false
  })

  const profile = data?.profile
  const reports = data?.reports || []
  const requestHistory = data?.requests || []

  useEffect(() => {
    if (!isOtherProfile && profile && !form) {
      setForm({
        full_name: profile.full_name,
        phone: profile.phone || '',
        date_of_birth: profile.date_of_birth || '',
        blood_type: profile.blood_type || ''
      })
      setLocation({
        location_id: profile.location?.location_id ?? null,
        municipality_code: profile.location?.municipality_code ?? null,
        barangay_code: profile.location?.barangay_code ?? null
      })
    }
  }, [form, isOtherProfile, profile])

  useEffect(() => setPictureFailed(false), [profile?.profile_picture_url])

  const showError = (error) => setErrorAlert(error?.message || 'The change could not be saved.')
  const clearNotices = () => { setMessage(null); setErrorAlert(null) }

  const setAvailability = async () => {
    const next = profile.availability === 'available' ? 'unavailable' : 'available'
    clearNotices()
    setAvailabilityUpdating(true)
    try {
      await api.post('/api/profile/donor-availability', { availability: next })
      await Promise.all([refetch(), refresh()])
      setMessage(next === 'available' ? 'You are now available to donate.' : 'You are now unavailable for donor matches.')
    } catch (error) {
      showError(error)
    } finally {
      setAvailabilityUpdating(false)
    }
  }

  const enrollAsDonor = async () => {
    clearNotices()
    setEnrolling(true)
    try {
      await api.post('/api/profile/enroll-donor')
      await Promise.all([refetch(), refresh()])
      setMessage('You are now enrolled and available for compatible donor matches.')
    } catch (error) {
      showError(error)
    } finally {
      setEnrolling(false)
    }
  }

  const onPictureUpload = async (pictureFile) => {
    if (!pictureFile) return
    clearNotices()
    setPictureUploading(true)
    try {
      await api.upload('/api/profile/picture', pictureFile)
      setPictureInputKey((key) => key + 1)
      await Promise.all([refetch(), refresh()])
      setMessage('Profile picture updated.')
    } catch (error) {
      showError(error)
    } finally {
      setPictureUploading(false)
    }
  }

  const setField = (name) => (event) => setForm((current) => ({ ...current, [name]: event.target.value }))

  const onSave = async (event) => {
    event.preventDefault()
    clearNotices()
    setErrors({})
    if (!location.location_id) {
      setErrors({ location_id: ['Please select your municipality or city.'] })
      return
    }
    setSubmitting(true)
    try {
      await api.put('/api/profile', {
        full_name: form.full_name,
        phone: form.phone || null,
        date_of_birth: form.date_of_birth || null,
        blood_type: form.blood_type || null,
        location_id: location.location_id
      })
      await Promise.all([refetch(), refresh()])
      setMessage('Profile details saved.')
    } catch (error) {
      if (error.details && Object.keys(error.details).length) setErrors(error.details)
      else showError(error)
    } finally {
      setSubmitting(false)
    }
  }

  const onUpload = async (event) => {
    event.preventDefault()
    clearNotices()
    if (!file) {
      setErrorAlert('Select a National ID file to upload.')
      return
    }
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
    if (!allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
      setErrorAlert('Choose a JPG, PNG, WEBP, or PDF file no larger than 5 MB.')
      return
    }
    setDocumentUploading(true)
    try {
      await api.upload('/api/profile/documents', file, { doc_type: 'national_id', privacy_acknowledged: '1' })
      setFile(null)
      setDocumentInputKey((key) => key + 1)
      await refetch()
      setMessage('National ID submitted for review.')
    } catch (error) {
      showError(error)
    } finally {
      setDocumentUploading(false)
    }
  }

  if (isLoading && !profile) return <Card padding="lg" className={styles.loadingCard}><LoadingSpinner text="Loading profile…" /></Card>

  if (isOtherProfile && profileError) {
    return (
      <div className={styles.container}>
        <Card padding="lg" className={styles.errorCard}>
          <h1>{profileError.status === 404 ? 'This profile isn’t available' : 'Profile unavailable'}</h1>
          <p>{profileError.message}</p>
          <Button variant="secondary" onClick={() => navigate(-1)}><ArrowLeft size={16} /> Go back</Button>
        </Card>
      </div>
    )
  }

  if (isOtherProfile) {
    return (
      <div className={styles.container}>
        <div className={styles.backRow}><Button variant="secondary" size="sm" onClick={() => navigate(-1)}><ArrowLeft size={16} /> Back</Button></div>
        <header className={styles.profileHeader}>
          <div className={styles.headerInner}>
            <div className={styles.avatarWrapper}>
              {profile.profile_picture_url ? <img className={styles.avatarLarge} src={profile.profile_picture_url} alt={`${profile.full_name}'s profile picture`} /> : <span className={styles.avatarLarge} role="img" aria-label="No profile picture"><User size={64} aria-hidden="true" /></span>}
            </div>
            <div className={styles.headerIdentity}>
              <div className={styles.nameLine}><h1>{profile.full_name}</h1>{profile.verification_status === 'verified' && <CheckCircle size={23} weight="fill" aria-label="Verified account" />}</div>
              <p>{profile.chapter_name ? `${profile.chapter_name} member` : profile.role_label}</p>
            </div>
          </div>
        </header>
        <main className={styles.publicDetails}>
          <Card>
            <div className={styles.cardHeader}><h2>Account details</h2></div>
            <dl className={styles.detailsGrid}>
              <div><dt>Email</dt><dd><a href={`mailto:${profile.email}`}>{profile.email}</a></dd></div>
              <div><dt>Blood type</dt><dd>{profile.blood_type || 'Not provided'}</dd></div>
              <div><dt>Chapter</dt><dd>{profile.chapter_name || 'Not assigned'}</dd></div>
              <div><dt>Role</dt><dd>{profile.role_label}</dd></div>
              <div><dt>Member since</dt><dd>{formatDate(profile.member_since)}</dd></div>
              <div><dt>Verification</dt><dd>{titleCase(profile.verification_status)}</dd></div>
            </dl>
          </Card>
        </main>
      </div>
    )
  }

  if (!form) return <Card padding="lg" className={styles.loadingCard}><LoadingSpinner text="Loading profile…" /></Card>

  const nationalIdDocuments = (profile.documents || [])
    .filter((document) => document.doc_type === 'national_id')
    .sort((a, b) => b.id - a.id)
  const availabilityBlocked = Boolean(profile.availability_window?.blocked)
  const availabilityLabel = profile.availability === 'available' ? 'Set unavailable' : 'Set available'
  const availabilityStatus = availabilityBlocked
    ? `${titleCase(profile.availability_window.which)} until ${formatDate(profile.availability_window.ends_at_utc)}`
    : profile.donor_enrolled
      ? (profile.availability === 'available' ? 'Available to donate' : 'Unavailable for donor matches')
      : 'Not enrolled as a donor'

  return (
    <div className={styles.container}>
      <div className={styles.notices}>
        {message && <StatusPill variant="active">{message}</StatusPill>}
        {errorAlert && <StatusPill variant="error">{errorAlert}</StatusPill>}
      </div>

      {profile.verification_status === 'rejected' && (
        <div className={`${styles.alertBox} ${styles.alertDanger}`}>
          <div><strong>Verification needs attention</strong><p>Upload a clearer or valid National ID to request another review.</p></div>
          <Button variant="primary" onClick={() => document.getElementById('verification-section')?.scrollIntoView({ behavior: 'smooth' })}>Upload another ID</Button>
        </div>
      )}

      <header className={styles.profileHeader}>
        <div className={styles.headerInner}>
          <div className={styles.avatarWrapper}>
            {profile.profile_picture_url && !pictureFailed ? (
              <img className={styles.avatarLarge} src={profile.profile_picture_url} alt={`${profile.full_name}'s profile picture`} onError={() => setPictureFailed(true)} />
            ) : (
              <span className={styles.avatarLarge} role="img" aria-label="No profile picture"><User size={64} aria-hidden="true" /></span>
            )}
            <label className={styles.avatarCameraOverlay} htmlFor="profile-picture" title="Update profile picture">
              {pictureUploading ? <span className="spinner" aria-hidden="true" /> : <Camera size={19} weight="fill" aria-hidden="true" />}
            </label>
            <input key={pictureInputKey} id="profile-picture" className={styles.visuallyHidden} type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onChange={(event) => onPictureUpload(event.target.files?.[0])} />
          </div>

          <div className={styles.headerIdentity}>
            <div className={styles.nameLine}>
              <h1>{profile.full_name}</h1>
              {profile.verification_status === 'verified' && <CheckCircle size={23} weight="fill" aria-label="Verified account" />}
            </div>
            <p>{profile.chapter_name ? `${profile.chapter_name} member` : titleCase(profile.role)}</p>
            {profile.role === 'member' && <span className={styles.availabilityText}><Drop size={16} weight="fill" aria-hidden="true" /> {availabilityStatus}</span>}
          </div>

          <div className={styles.headerActions}>
            {profile.role === 'member' && profile.verification_status !== 'verified' && <Button variant="secondary" disabled>Verification required</Button>}
            {profile.role === 'member' && profile.verification_status === 'verified' && !profile.donor_enrolled && <Button variant="secondary" onClick={enrollAsDonor} isLoading={enrolling}>Enroll as donor</Button>}
            {profile.role === 'member' && profile.donor_enrolled && <Button variant="secondary" disabled={availabilityBlocked} onClick={setAvailability} isLoading={availabilityUpdating}>{availabilityBlocked ? 'Availability locked' : availabilityLabel}</Button>}
            <Button onClick={() => navigate('/requests/new')}>Request blood</Button>
          </div>
        </div>
      </header>

      <main className={styles.overview}>
        <Card>
          <div className={styles.cardHeader}><div><h2>Personal and location</h2><p>Keep your matching details accurate.</p></div></div>
          <form onSubmit={onSave} noValidate className={styles.formStack}>
            <Input label="Full name" id="full_name" value={form.full_name} onChange={setField('full_name')} required maxLength={150} error={errors.full_name?.join(' ')} />
            <div className={styles.twoColumns}>
              <Input label="Phone number" id="phone" type="tel" value={form.phone} onChange={setField('phone')} placeholder="0917-123-4567" error={errors.phone?.join(' ')} />
              <Input label="Date of birth" id="date_of_birth" type="date" value={form.date_of_birth} onChange={setField('date_of_birth')} error={errors.date_of_birth?.join(' ')} />
            </div>
            <div className={styles.fieldGroup}>
              <label htmlFor="blood_type">Blood type</label>
              <select id="blood_type" value={form.blood_type} onChange={setField('blood_type')}>
                <option value="">Unknown / Not sure</option>
                {BLOOD_TYPES.map((type) => <option key={type} value={type}>{type}</option>)}
              </select>
              {errors.blood_type && <span className={styles.fieldError}>{errors.blood_type.join(' ')}</span>}
            </div>
            <div className={styles.fieldGroup}>
              <span>Location in Bataan</span>
              <LocationSelector municipalityId="profile-municipality" municipalityCode={location.municipality_code} barangayCode={location.barangay_code} onChange={setLocation} errors={errors} />
            </div>
            <Button type="submit" isLoading={submitting} className={styles.saveButton}>Save changes</Button>
          </form>
        </Card>

        {profile.role === 'member' && (
          <Card id="verification-section" className={styles.verificationCard}>
            <div className={styles.cardHeader}>
              <div><h2>Verification</h2><p>Your account status and submitted identification.</p></div>
              <Badge variant={profile.verification_status === 'verified' ? 'success' : profile.verification_status === 'rejected' ? 'critical' : 'neutral'}>{titleCase(profile.verification_status)}</Badge>
            </div>
            {nationalIdDocuments.length === 0 || profile.verification_status === 'rejected' ? (
              <form onSubmit={onUpload} className={styles.verificationUpload}>
                <FileText size={30} weight="duotone" aria-hidden="true" />
                <div><h3>{profile.verification_status === 'rejected' ? 'Choose a replacement ID' : 'Upload your National ID'}</h3><p>Use a clear JPG, PNG, WEBP, or PDF up to 5 MB.</p></div>
                <input key={documentInputKey} id="profile-national-id" className={styles.visuallyHidden} type="file" accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(event) => setFile(event.target.files?.[0] || null)} />
                <label htmlFor="profile-national-id" className={styles.filePicker}><UploadSimple size={18} /> {file ? 'Choose another file' : 'Choose a file'}</label>
                {file && <span className={styles.fileName}>{file.name}</span>}
                {file && <Button type="submit" isLoading={documentUploading}>{profile.verification_status === 'rejected' ? 'Request another review' : 'Submit for review'}</Button>}
                <button type="button" className={styles.privacyLink} onClick={() => setIdPrivacyModalOpen(true)}>Review the privacy notice</button>
              </form>
            ) : (
              <div className={styles.verificationComplete}>
                <CheckCircle size={30} weight="duotone" aria-hidden="true" />
                <div><h3>{profile.verification_status === 'verified' ? 'National ID verified' : 'Review in progress'}</h3><p>{profile.verification_status === 'verified' ? 'Your account verification is complete.' : 'You will receive a notification when the review is complete.'}</p></div>
                {nationalIdDocuments[0] && <Button to={`/api/profile/documents/${nationalIdDocuments[0].id}/file`} target="_blank" rel="noreferrer" variant="secondary" size="sm">View uploaded ID</Button>}
              </div>
            )}
          </Card>
        )}

        {profile.role === 'member' && (
          <section className={styles.historySection} aria-labelledby="history-heading">
            <div className={styles.sectionHeading}><h2 id="history-heading">History</h2><p>Your donation and blood-request activity are kept separate.</p></div>

            <Card>
              <div className={styles.cardHeader}><div><h2>Donation history</h2><p>Reports submitted for officer confirmation.</p></div></div>
              {reports.length === 0 ? <p className={styles.emptyText}>No donation reports recorded yet.</p> : (
                <div className={styles.historyList}>
                  {reports.map((report) => (
                    <article key={report.id} className={styles.historyRow}>
                      <div className={styles.historyBlood}>{report.required_blood_type}</div>
                      <div><h3>{report.facility_name}</h3><p>{formatDate(report.reported_at)}</p></div>
                      <Badge variant={report.status === 'CONFIRMED' ? 'success' : report.status === 'REJECTED' ? 'critical' : 'urgent'}>{titleCase(report.status)}</Badge>
                    </article>
                  ))}
                </div>
              )}
            </Card>

            <Card>
              <div className={styles.cardHeader}><div><h2>Request history</h2><p>Requests created from this account.</p></div><Button to="/requests/mine" variant="secondary" size="sm">Manage requests</Button></div>
              {requestHistory.length === 0 ? <p className={styles.emptyText}>No blood requests recorded yet.</p> : (
                <div className={styles.historyList}>
                  {requestHistory.map((request) => (
                    <article key={request.id} className={styles.historyRow}>
                      <div className={styles.historyBlood}>{request.required_blood_type}</div>
                      <div><h3>{request.facility_name}</h3><p>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'} · Needed {formatDate(request.needed_datetime)}</p></div>
                      <Badge variant={request.status === 'FULFILLED' ? 'success' : request.status === 'OPEN' ? 'brand' : 'neutral'}>{titleCase(request.status)}</Badge>
                    </article>
                  ))}
                </div>
              )}
            </Card>
          </section>
        )}
      </main>

      <PrivacyConsentModal isOpen={idPrivacyModalOpen} onClose={() => setIdPrivacyModalOpen(false)} readonly />
    </div>
  )
}
