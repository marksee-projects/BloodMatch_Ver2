import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  ArrowLeft,
  ArrowRight,
  ArrowsClockwise,
  Calendar,
  Check,
  CheckCircle,
  FilePdf,
  ShieldCheck,
  Trash,
  UploadSimple,
  WarningCircle
} from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import PrivacyNoticeModal, {
  REGISTRATION_PRIVACY_TITLE,
  RegistrationPrivacyBody
} from '../components/PrivacyNoticeModal'
import styles from './RegisterPage.module.css'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

export default function RegisterPage() {
  const navigate = useNavigate()
  const dateInputRef = useRef(null)

  const [step, setStep] = useState(1)

  const [form, setForm] = useState({
    full_name: '',
    email: '',
    password: '',
    password_confirm: '',
    chapter_id: '',
    date_of_birth: '',
    phone: '',
    blood_type: ''
  })

  const [chapters, setChapters] = useState([])
  const [loadingChapters, setLoadingChapters] = useState(true)
  const [chapterError, setChapterError] = useState(null)

  const [idFile, setIdFile] = useState(null)
  const [idPreview, setIdPreview] = useState(null)
  const [idError, setIdError] = useState(null)
  const [dragOver, setDragOver] = useState(false)

  const [stepErrors, setStepErrors] = useState({})
  const [serverErrors, setServerErrors] = useState({})
  const [message, setMessage] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [privacyAck, setPrivacyAck] = useState(false)
  const [privacyModalOpen, setPrivacyModalOpen] = useState(false)

  const loadChapters = async () => {
    setLoadingChapters(true)
    setChapterError(null)
    try {
      const data = await api.get('/api/chapters')
      setChapters(data.chapters || [])
    } catch (err) {
      setChapterError(err.message || 'Unable to connect to server and load chapters.')
    } finally {
      setLoadingChapters(false)
    }
  }

  useEffect(() => {
    loadChapters()
  }, [])

  const setField = (name) => (e) => {
    setForm((f) => ({ ...f, [name]: e.target.value }))
    if (stepErrors[name]) {
      setStepErrors((prev) => {
        const next = { ...prev }
        delete next[name]
        return next
      })
    }
  }

  const validateFile = (file) => {
    if (!file) return null
    if (file.size > 5 * 1024 * 1024) {
      return 'National ID file exceeds maximum size of 5 MB.'
    }
    const allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
    if (!allowed.includes(file.type)) {
      return 'Invalid file type. Please upload a JPG, PNG, WEBP picture or PDF.'
    }
    return null
  }

  const processFile = (file) => {
    const error = validateFile(file)
    if (error) {
      setIdError(error)
      return
    }
    setIdError(null)
    setIdFile(file)
    if (file.type.startsWith('image/')) {
      setIdPreview(URL.createObjectURL(file))
    } else {
      setIdPreview(null)
    }
  }

  const handleFileChange = (e) => {
    const file = e.target.files?.[0]
    if (file) {
      processFile(file)
    }
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setDragOver(false)
    const file = e.dataTransfer.files?.[0]
    if (file) {
      processFile(file)
    }
  }

  const handleDragOver = (e) => {
    e.preventDefault()
    setDragOver(true)
  }

  const handleDragLeave = (e) => {
    e.preventDefault()
    setDragOver(false)
  }

  const removeIdFile = () => {
    setIdFile(null)
    if (idPreview) URL.revokeObjectURL(idPreview)
    setIdPreview(null)
    setIdError(null)
  }

  const calculateAge = (dobString) => {
    if (!dobString) return null
    const birth = new Date(dobString)
    if (isNaN(birth.getTime())) return null
    const today = new Date()
    let age = today.getFullYear() - birth.getFullYear()
    const m = today.getMonth() - birth.getMonth()
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
      age--
    }
    return age
  }

  const age = calculateAge(form.date_of_birth)

  const triggerDatePicker = () => {
    if (dateInputRef.current) {
      if (typeof dateInputRef.current.showPicker === 'function') {
        dateInputRef.current.showPicker()
      } else {
        dateInputRef.current.focus()
      }
    }
  }

  const validateStep = (currentStep) => {
    const errors = {}

    if (currentStep === 1) {
      if (!form.full_name.trim()) {
        errors.full_name = 'Full legal name is required.'
      } else if (form.full_name.trim().length < 2) {
        errors.full_name = 'Full name must be at least 2 characters.'
      }

      if (!form.email.trim()) {
        errors.email = 'Email address is required.'
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        errors.email = 'Please enter a valid email address.'
      }

      if (!form.password) {
        errors.password = 'Password is required.'
      } else {
        if (form.password.length < 8) {
          errors.password = 'Password must be at least 8 characters.'
        } else if (!/[A-Za-z]/.test(form.password) || !/\d/.test(form.password)) {
          errors.password = 'Password must contain at least one letter and one number.'
        }
      }

      if (!form.password_confirm) {
        errors.password_confirm = 'Please confirm your password.'
      } else if (form.password !== form.password_confirm) {
        errors.password_confirm = 'Passwords do not match.'
      }

      if (!form.chapter_id) {
        errors.chapter_id = 'Please select your assigned chapter.'
      }
    }

    if (currentStep === 2) {
      if (!form.date_of_birth) {
        errors.date_of_birth = 'Date of birth is required.'
      } else if (age !== null && age < 16) {
        errors.date_of_birth = 'Registration requires a minimum age of 16 years.'
      }

      if (idError) {
        errors.idFile = idError
      }
    }

    if (currentStep === 3) {
      if (!privacyAck) {
        errors.privacy_acknowledged = 'You must read and agree to the Privacy Notice to continue.'
      }
    }

    setStepErrors(errors)
    return Object.keys(errors).length === 0
  }

  const handleNext = () => {
    if (validateStep(step)) {
      setStep((s) => Math.min(s + 1, 3))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  const handleBack = () => {
    setStep((s) => Math.max(s - 1, 1))
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const selectedChapterName =
    chapters.find((c) => String(c.id) === String(form.chapter_id))?.name || 'Not selected'

  const onSubmit = async (e) => {
    e.preventDefault()
    if (!validateStep(3)) return

    setServerErrors({})
    setMessage(null)
    setSubmitting(true)

    try {
      if (idFile) {
        const fd = new FormData()
        fd.append('full_name', form.full_name.trim())
        fd.append('email', form.email.trim().toLowerCase())
        fd.append('password', form.password)
        fd.append('chapter_id', form.chapter_id)
        fd.append('date_of_birth', form.date_of_birth)
        if (form.blood_type) fd.append('blood_type', form.blood_type)
        if (form.phone) fd.append('phone', form.phone.trim())
        fd.append('privacy_acknowledged', 'true')
        fd.append('national_id', idFile)
        await api.postForm('/api/register', fd)
      } else {
        await api.post('/api/register', {
          full_name: form.full_name.trim(),
          email: form.email.trim().toLowerCase(),
          password: form.password,
          chapter_id: Number(form.chapter_id),
          date_of_birth: form.date_of_birth,
          blood_type: form.blood_type || null,
          phone: form.phone.trim() || null,
          privacy_acknowledged: true
        })
      }
      navigate('/login', { state: { registered: true } })
    } catch (err) {
      if (err.details && Object.keys(err.details).length > 0) {
        setServerErrors(err.details)
        // If error belongs to step 1 or 2, jump back to that step
        if (err.details.full_name || err.details.email || err.details.password || err.details.chapter_id) {
          setStep(1)
        } else if (err.details.date_of_birth || err.details.blood_type || err.details.phone) {
          setStep(2)
        }
      } else {
        setMessage(err.message || 'Registration failed.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const stepPercentage = step === 1 ? '0%' : step === 2 ? '50%' : '100%'

  return (
    <div className="container narrow" style={{ maxWidth: '640px', paddingBlock: 'var(--space-6)' }}>
      <div style={{ marginBottom: 'var(--space-6)', textAlign: 'center' }}>
        <h1 style={{ marginBottom: 'var(--space-2)' }}>Create your account</h1>
        <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
          Join the verified DeMolay Bataan blood donor network.
        </p>
      </div>

      {message && <div className="alert alert-error" role="alert">{message}</div>}

      {chapterError && (
        <div className="alert alert-error" role="alert" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 'var(--space-2)' }}>
          <span>{chapterError}</span>
          <button type="button" className="btn btn-sm btn-secondary" onClick={loadChapters} style={{ flexShrink: 0 }}>
            <ArrowsClockwise size={14} style={{ marginRight: '4px' }} /> Retry
          </button>
        </div>
      )}

      {/* 3-Step Wizard Card */}
      <div className={styles.wizardCard}>
        {/* Stepper Header */}
        <div className={styles.stepperContainer}>
          <div className={styles.stepperTrack}>
            <div className={styles.stepperLineBg} />
            <div className={styles.stepperLineFill} style={{ width: `calc(${stepPercentage} * 0.88)` }} />

            {/* Step 1 Node */}
            <button
              type="button"
              className={`${styles.stepItem} ${step === 1 ? styles.stepActive : step > 1 ? styles.stepCompleted : ''}`}
              onClick={() => step > 1 && setStep(1)}
              aria-label="Step 1: Account Setup"
            >
              <div className={styles.stepBubble}>
                {step > 1 ? <Check size={18} weight="bold" /> : '1'}
              </div>
              <span className={styles.stepLabel}>Account</span>
            </button>

            {/* Step 2 Node */}
            <button
              type="button"
              className={`${styles.stepItem} ${step === 2 ? styles.stepActive : step > 2 ? styles.stepCompleted : ''}`}
              onClick={() => {
                if (step > 2) setStep(2)
                else if (step === 1 && validateStep(1)) setStep(2)
              }}
              aria-label="Step 2: Personal Details & ID"
            >
              <div className={styles.stepBubble}>
                {step > 2 ? <Check size={18} weight="bold" /> : '2'}
              </div>
              <span className={styles.stepLabel}>Details &amp; ID</span>
            </button>

            {/* Step 3 Node */}
            <button
              type="button"
              className={`${styles.stepItem} ${step === 3 ? styles.stepActive : ''}`}
              onClick={() => {
                if (validateStep(1) && validateStep(2)) setStep(3)
              }}
              aria-label="Step 3: Review & Consent"
            >
              <div className={styles.stepBubble}>3</div>
              <span className={styles.stepLabel}>Review &amp; Consent</span>
            </button>
          </div>
        </div>

        {/* Wizard Forms */}
        <form onSubmit={step === 3 ? onSubmit : (e) => { e.preventDefault(); handleNext() }} noValidate>
          {/* STEP 1: ACCOUNT SETUP */}
          {step === 1 && (
            <div className="form">
              <div className="field">
                <label htmlFor="full_name">Full legal name *</label>
                <input
                  id="full_name"
                  autoComplete="name"
                  placeholder="e.g. Maria Santos"
                  value={form.full_name}
                  onChange={setField('full_name')}
                  required
                  maxLength={150}
                  autoFocus
                  aria-invalid={!!(stepErrors.full_name || serverErrors.full_name)}
                />
                {(stepErrors.full_name || serverErrors.full_name) && (
                  <span className="field-error">
                    {stepErrors.full_name || serverErrors.full_name?.join(' ')}
                  </span>
                )}
              </div>

              <div className="field">
                <label htmlFor="email">Email address *</label>
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  placeholder="name@example.com"
                  value={form.email}
                  onChange={setField('email')}
                  required
                  aria-invalid={!!(stepErrors.email || serverErrors.email)}
                />
                {(stepErrors.email || serverErrors.email) && (
                  <span className="field-error">
                    {stepErrors.email || serverErrors.email?.join(' ')}
                  </span>
                )}
              </div>

              <div className="grid-2">
                <div className="field">
                  <label htmlFor="password">Password *</label>
                  <input
                    id="password"
                    type="password"
                    autoComplete="new-password"
                    placeholder="Min 8 chars with letter & number"
                    value={form.password}
                    onChange={setField('password')}
                    required
                    minLength={8}
                    aria-describedby="pwd-hint"
                    aria-invalid={!!(stepErrors.password || serverErrors.password)}
                  />
                  <small id="pwd-hint" className="field-hint">Min 8 chars, letter &amp; number.</small>
                  {(stepErrors.password || serverErrors.password) && (
                    <span className="field-error">
                      {stepErrors.password || serverErrors.password?.join(' ')}
                    </span>
                  )}
                </div>

                <div className="field">
                  <label htmlFor="password_confirm">Confirm password *</label>
                  <input
                    id="password_confirm"
                    type="password"
                    autoComplete="new-password"
                    placeholder="Re-enter password"
                    value={form.password_confirm}
                    onChange={setField('password_confirm')}
                    required
                    aria-invalid={!!stepErrors.password_confirm}
                  />
                  {stepErrors.password_confirm && (
                    <span className="field-error">{stepErrors.password_confirm}</span>
                  )}
                </div>
              </div>

              <div className="field">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <label htmlFor="chapter_id">Assigned Chapter *</label>
                  {loadingChapters && (
                    <span className="muted" style={{ fontSize: 'var(--text-xs)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                      <ArrowsClockwise size={12} className="spinning" /> Loading chapters...
                    </span>
                  )}
                </div>
                <select
                  id="chapter_id"
                  value={form.chapter_id}
                  onChange={setField('chapter_id')}
                  required
                  disabled={loadingChapters || chapters.length === 0}
                  aria-invalid={!!(stepErrors.chapter_id || serverErrors.chapter_id)}
                >
                  <option value="">
                    {loadingChapters ? 'Loading local chapters…' : 'Select your local chapter…'}
                  </option>
                  {chapters.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} ({c.municipality})
                    </option>
                  ))}
                </select>
                {(stepErrors.chapter_id || serverErrors.chapter_id) && (
                  <span className="field-error">
                    {stepErrors.chapter_id || serverErrors.chapter_id?.join(' ')}
                  </span>
                )}
              </div>

              <div className={styles.actionRow} style={{ justifyContent: 'flex-end' }}>
                <button
                  type="button"
                  className="btn"
                  onClick={handleNext}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                >
                  Next: Personal Details &amp; ID <ArrowRight size={16} />
                </button>
              </div>
            </div>
          )}

          {/* STEP 2: PERSONAL DETAILS & ID UPLOAD */}
          {step === 2 && (
            <div className="form">
              <div className="grid-2">
                <div className="field">
                  <label htmlFor="date_of_birth">Date of birth *</label>
                  <div style={{ position: 'relative', display: 'flex', alignItems: 'center' }}>
                    <input
                      ref={dateInputRef}
                      id="date_of_birth"
                      type="date"
                      autoComplete="bday"
                      max={new Date().toISOString().slice(0, 10)}
                      min="1920-01-01"
                      value={form.date_of_birth}
                      onChange={setField('date_of_birth')}
                      required
                      aria-describedby="dob-hint"
                      style={{ paddingRight: '2.5rem' }}
                      aria-invalid={!!(stepErrors.date_of_birth || serverErrors.date_of_birth)}
                    />
                    <button
                      type="button"
                      onClick={triggerDatePicker}
                      aria-label="Open calendar picker"
                      title="Open calendar picker"
                      style={{
                        position: 'absolute',
                        right: '0.6rem',
                        background: 'none',
                        border: 'none',
                        cursor: 'pointer',
                        color: 'var(--color-text-muted)',
                        display: 'flex',
                        alignItems: 'center',
                        padding: '4px'
                      }}
                    >
                      <Calendar size={18} weight="bold" />
                    </button>
                  </div>

                  {age !== null && (
                    <div style={{ marginTop: 'var(--space-1)' }}>
                      {age < 16 ? (
                        <span className="field-error" style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <WarningCircle size={14} weight="fill" /> Age: {age} — Ineligible (minimum age is 16)
                        </span>
                      ) : age < 18 ? (
                        <span style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text)', display: 'flex', alignItems: 'center', gap: '4px', background: 'var(--color-surface-hover)', padding: '2px 8px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--color-border)' }}>
                          <WarningCircle size={14} weight="bold" /> Age: {age} — Eligible with Parental Consent
                        </span>
                      ) : (
                        <span style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text)', display: 'flex', alignItems: 'center', gap: '4px', background: 'var(--color-surface-hover)', padding: '2px 8px', borderRadius: 'var(--radius-sm)', border: '1px solid var(--color-border)' }}>
                          <CheckCircle size={14} weight="fill" /> Age: {age} — Eligible volunteer donor
                        </span>
                      )}
                    </div>
                  )}
                  <small id="dob-hint" className="field-hint">Ages 16–17 require parental consent doc upon verification.</small>
                  {(stepErrors.date_of_birth || serverErrors.date_of_birth) && (
                    <span className="field-error">
                      {stepErrors.date_of_birth || serverErrors.date_of_birth?.join(' ')}
                    </span>
                  )}
                </div>

                <div className="field">
                  <label htmlFor="blood_type">Blood type (self-reported)</label>
                  <select
                    id="blood_type"
                    value={form.blood_type}
                    onChange={setField('blood_type')}
                  >
                    <option value="">Unknown / not sure</option>
                    {BLOOD_TYPES.map((bt) => (
                      <option key={bt} value={bt}>{bt}</option>
                    ))}
                  </select>
                  <small className="field-hint">Marked self-reported until officer verified.</small>
                  {serverErrors.blood_type && (
                    <span className="field-error">{serverErrors.blood_type.join(' ')}</span>
                  )}
                </div>
              </div>

              <div className="field">
                <label htmlFor="phone">Phone number</label>
                <input
                  id="phone"
                  type="tel"
                  autoComplete="tel"
                  placeholder="e.g. 0917-123-4567"
                  value={form.phone}
                  onChange={setField('phone')}
                />
                {serverErrors.phone && (
                  <span className="field-error">{serverErrors.phone.join(' ')}</span>
                )}
              </div>

              {/* National ID / Student Card Drag-and-Drop Zone */}
              <div className="field" style={{ marginTop: 'var(--space-2)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 'var(--space-1)' }}>
                  <label htmlFor="national_id_input" style={{ fontWeight: 600 }}>
                    National ID / Student ID Picture
                  </label>
                  <span className="muted" style={{ fontSize: 'var(--text-xs)' }}>Recommended for fast verification</span>
                </div>

                {!idFile ? (
                  <div
                    className={`${styles.dropzone} ${dragOver ? styles.dropzoneDragOver : ''}`}
                    onDragOver={handleDragOver}
                    onDragLeave={handleDragLeave}
                    onDrop={handleDrop}
                  >
                    <label
                      htmlFor="national_id_input"
                      style={{ cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 'var(--space-2)', width: '100%' }}
                    >
                      <UploadSimple size={28} weight="bold" style={{ color: 'var(--color-text-muted)' }} />
                      <div>
                        <span style={{ fontWeight: 600, textDecoration: 'underline' }}>Click to upload</span> or drag and drop ID here
                        <div className="muted" style={{ fontSize: 'var(--text-xs)', marginTop: '2px' }}>
                          JPG, PNG, WEBP or PDF (max 5 MB)
                        </div>
                      </div>
                      <input
                        id="national_id_input"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        onChange={handleFileChange}
                        style={{ display: 'none' }}
                      />
                    </label>
                  </div>
                ) : (
                  <div className={styles.filePreviewCard}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)' }}>
                      {idPreview ? (
                        <img
                          src={idPreview}
                          alt="National ID Preview"
                          style={{
                            width: '48px',
                            height: '48px',
                            objectFit: 'cover',
                            borderRadius: 'var(--radius-sm)',
                            border: '1px solid var(--color-border)'
                          }}
                        />
                      ) : (
                        <div
                          style={{
                            width: '48px',
                            height: '48px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            background: 'var(--color-surface-hover)',
                            borderRadius: 'var(--radius-sm)'
                          }}
                        >
                          <FilePdf size={28} weight="fill" />
                        </div>
                      )}
                      <div>
                        <div style={{ fontWeight: 600, fontSize: 'var(--text-sm)' }}>{idFile.name}</div>
                        <div className="muted" style={{ fontSize: 'var(--text-xs)' }}>
                          {(idFile.size / 1024).toFixed(1)} KB · Ready to upload
                        </div>
                      </div>
                    </div>

                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      onClick={removeIdFile}
                      title="Remove file"
                      aria-label="Remove uploaded ID file"
                      style={{ color: 'var(--color-danger)' }}
                    >
                      <Trash size={16} />
                    </button>
                  </div>
                )}

                {idError && <span className="field-error">{idError}</span>}
                <small className="field-hint">
                  Your ID document is securely encrypted and accessible only to assigned chapter officers for verification.
                </small>
              </div>

              <div className={styles.actionRow}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={handleBack}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                >
                  <ArrowLeft size={16} /> Back
                </button>
                <button
                  type="button"
                  className="btn"
                  onClick={handleNext}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                >
                  Next: Review &amp; Consent <ArrowRight size={16} />
                </button>
              </div>
            </div>
          )}

          {/* STEP 3: REVIEW & CONSENT */}
          {step === 3 && (
            <div className="form">
              {/* Summary Cards */}
              <div className={styles.summarySection}>
                <div className={styles.summaryHeader}>
                  <h3 className={styles.summaryTitle}>Account Information</h3>
                  <button
                    type="button"
                    className={styles.editButton}
                    onClick={() => setStep(1)}
                  >
                    Edit
                  </button>
                </div>
                <div className={styles.summaryGrid}>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>Full Name</span>
                    <span className={styles.summaryItemValue}>{form.full_name || '—'}</span>
                  </div>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>Email</span>
                    <span className={styles.summaryItemValue}>{form.email || '—'}</span>
                  </div>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>Assigned Chapter</span>
                    <span className={styles.summaryItemValue}>{selectedChapterName}</span>
                  </div>
                </div>
              </div>

              <div className={styles.summarySection}>
                <div className={styles.summaryHeader}>
                  <h3 className={styles.summaryTitle}>Personal &amp; Contact Details</h3>
                  <button
                    type="button"
                    className={styles.editButton}
                    onClick={() => setStep(2)}
                  >
                    Edit
                  </button>
                </div>
                <div className={styles.summaryGrid}>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>Date of Birth</span>
                    <span className={styles.summaryItemValue}>
                      {form.date_of_birth ? `${form.date_of_birth} (Age: ${age})` : '—'}
                    </span>
                  </div>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>Blood Type</span>
                    <span className={styles.summaryItemValue}>{form.blood_type || 'Unknown'}</span>
                  </div>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>Phone</span>
                    <span className={styles.summaryItemValue}>{form.phone || 'None provided'}</span>
                  </div>
                  <div className={styles.summaryItem}>
                    <span className={styles.summaryItemLabel}>ID Document</span>
                    <span className={styles.summaryItemValue}>
                      {idFile ? `${idFile.name} (${(idFile.size / 1024).toFixed(1)} KB)` : 'None (optional)'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Task 4: Clean single checkbox line with hyperlinked Privacy Notice modal trigger */}
              <div className="check-row" style={{ marginTop: 'var(--space-4)' }}>
                <input
                  id="register-privacy-ack"
                  type="checkbox"
                  checked={privacyAck}
                  onChange={(e) => {
                    setPrivacyAck(e.target.checked)
                    if (e.target.checked) {
                      setStepErrors((prev) => {
                        const next = { ...prev }
                        delete next.privacy_acknowledged
                        return next
                      })
                    }
                  }}
                  aria-describedby="register-privacy-hint"
                />
                <label htmlFor="register-privacy-ack">
                  I have read and agree to the{' '}
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault()
                      setPrivacyModalOpen(true)
                    }}
                    style={{
                      background: 'none',
                      border: 'none',
                      padding: 0,
                      color: 'var(--color-text)',
                      textDecoration: 'underline',
                      fontWeight: 650,
                      cursor: 'pointer',
                      font: 'inherit'
                    }}
                  >
                    BloodMatch Privacy Notice
                  </button>{' '}
                  and terms of processing personal data under the Data Privacy Act of 2012.
                </label>
              </div>

              {stepErrors.privacy_acknowledged && (
                <span className="field-error" style={{ marginTop: 'var(--space-2)' }}>
                  {stepErrors.privacy_acknowledged}
                </span>
              )}
              {serverErrors.privacy_acknowledged && (
                <span className="field-error" style={{ marginTop: 'var(--space-2)' }}>
                  {serverErrors.privacy_acknowledged.join(' ')}
                </span>
              )}

              <div className={styles.actionRow}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={handleBack}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
                >
                  <ArrowLeft size={16} /> Back
                </button>
                <button
                  type="submit"
                  className="btn btn-lg"
                  disabled={submitting || !privacyAck}
                  style={{ minWidth: '160px' }}
                >
                  {submitting ? (idFile ? 'Creating account & uploading ID…' : 'Creating account…') : 'Create account'}
                </button>
              </div>
            </div>
          )}
        </form>
      </div>

      {/* Full Privacy Notice Modal */}
      <PrivacyNoticeModal
        open={privacyModalOpen}
        title={REGISTRATION_PRIVACY_TITLE}
        checkboxLabel="I have read and understood the BloodMatch Privacy Notice and acknowledge the processing of my personal information for the purposes stated above."
        checkboxId="register-privacy-ack-modal"
        acknowledged={privacyAck}
        onAcknowledgeChange={(v) => {
          setPrivacyAck(v)
          if (v) {
            setStepErrors((prev) => {
              const next = { ...prev }
              delete next.privacy_acknowledged
              return next
            })
          }
        }}
        onClose={() => setPrivacyModalOpen(false)}
        onConfirm={() => setPrivacyModalOpen(false)}
        confirmLabel="Confirm &amp; Continue"
        Body={RegistrationPrivacyBody}
      />

      <p className="muted text-center" style={{ marginTop: 'var(--space-6)' }}>
        Already have an account? <Link to="/login" style={{ fontWeight: 600 }}>Sign in</Link>
      </p>
    </div>
  )
}
