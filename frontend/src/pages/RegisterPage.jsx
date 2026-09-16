import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ArrowsClockwise, Calendar, CheckCircle, FilePdf, Trash, UploadSimple, WarningCircle } from '@phosphor-icons/react'
import { api } from '../services/apiClient'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

export default function RegisterPage() {
  const navigate = useNavigate()
  const dateInputRef = useRef(null)

  const [form, setForm] = useState({
    full_name: '',
    email: '',
    password: '',
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

  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const loadChapters = async () => {
    setLoadingChapters(true)
    setChapterError(null)
    try {
      const data = await api.get('/api/chapters')
      setChapters(data.chapters || [])
    } catch (err) {
      setChapterError(err.message || 'Unable to connect to server and load chapters. Make sure the backend server is running on port 8000.')
    } finally {
      setLoadingChapters(false)
    }
  }

  useEffect(() => {
    loadChapters()
  }, [])

  const setField = (name) => (e) => setForm((f) => ({ ...f, [name]: e.target.value }))

  const handleFileChange = (e) => {
    const file = e.target.files?.[0]
    setIdError(null)
    if (!file) {
      setIdFile(null)
      if (idPreview) URL.revokeObjectURL(idPreview)
      setIdPreview(null)
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      setIdError('National ID file exceeds maximum size of 5 MB.')
      e.target.value = ''
      return
    }

    const allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
    if (!allowed.includes(file.type)) {
      setIdError('Invalid file type. Please upload a JPG, PNG, WEBP picture or PDF.')
      e.target.value = ''
      return
    }

    setIdFile(file)
    if (file.type.startsWith('image/')) {
      setIdPreview(URL.createObjectURL(file))
    } else {
      setIdPreview(null)
    }
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

  const onSubmit = async (e) => {
    e.preventDefault()
    setErrors({})
    setMessage(null)

    if (age !== null && age < 16) {
      setMessage('Registration requires a minimum age of 16 years.')
      return
    }

    setSubmitting(true)
    try {
      const fd = new FormData()
      fd.append('full_name', form.full_name)
      fd.append('email', form.email)
      fd.append('password', form.password)
      fd.append('chapter_id', form.chapter_id)
      fd.append('date_of_birth', form.date_of_birth)
      if (form.blood_type) fd.append('blood_type', form.blood_type)
      if (form.phone) fd.append('phone', form.phone)
      if (idFile) {
        fd.append('national_id', idFile)
      }

      await api.postForm('/api/register', fd)
      navigate('/login', { state: { registered: true } })
    } catch (err) {
      if (err.details && Object.keys(err.details).length > 0) {
        setErrors(err.details)
      } else {
        setMessage(err.message || 'Registration failed.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="container narrow" style={{ maxWidth: '640px', paddingBlock: 'var(--space-6)' }}>
      <div style={{ marginBottom: 'var(--space-6)', textAlign: 'center' }}>
        <h1 style={{ marginBottom: 'var(--space-2)' }}>Create your account</h1>
        <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
          Join the DeMolay Bataan verified blood donor registry.
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

      <div className="card">
        <form className="form" onSubmit={onSubmit} noValidate>
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
              aria-describedby={errors.full_name ? 'full_name_err' : undefined}
            />
            {errors.full_name && <span id="full_name_err" className="field-error">{errors.full_name.join(' ')}</span>}
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
              aria-describedby={errors.email ? 'email_err' : undefined}
            />
            {errors.email && <span id="email_err" className="field-error">{errors.email.join(' ')}</span>}
          </div>

          <div className="field">
            <label htmlFor="password">Password *</label>
            <input
              id="password"
              type="password"
              autoComplete="new-password"
              placeholder="At least 8 chars with letters and numbers"
              value={form.password}
              onChange={setField('password')}
              required
              minLength={8}
              aria-describedby="pwd-hint"
            />
            <small id="pwd-hint" className="field-hint">Must contain at least 8 characters, with at least one letter and one number.</small>
            {errors.password && <span className="field-error">{errors.password.join(' ')}</span>}
          </div>

          {/* Chapter selection with resilient states */}
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
              aria-describedby={errors.chapter_id ? 'chapter_err' : undefined}
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
            {errors.chapter_id && <span id="chapter_err" className="field-error">{errors.chapter_id.join(' ')}</span>}
          </div>

          {/* Date of birth with interactive calendar picker & live age calculation */}
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

              {/* Live Age & Eligibility Indicator */}
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
              {errors.date_of_birth && <span className="field-error">{errors.date_of_birth.join(' ')}</span>}
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
              <small className="field-hint">Marked self-reported until verified by chapter officer.</small>
              {errors.blood_type && <span className="field-error">{errors.blood_type.join(' ')}</span>}
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
            {errors.phone && <span className="field-error">{errors.phone.join(' ')}</span>}
          </div>

          {/* National ID Picture Upload Zone */}
          <div className="field" style={{ borderTop: '1px solid var(--color-border-subtle)', paddingTop: 'var(--space-4)', marginTop: 'var(--space-2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 'var(--space-1)' }}>
              <label htmlFor="national_id_input" style={{ fontWeight: 600 }}>National ID / Student ID Picture</label>
              <span className="muted" style={{ fontSize: 'var(--text-xs)' }}>Recommended for fast verification</span>
            </div>

            {!idFile ? (
              <label
                htmlFor="national_id_input"
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: 'var(--space-2)',
                  padding: 'var(--space-5)',
                  border: '2px dashed var(--color-border)',
                  borderRadius: 'var(--radius-md)',
                  background: 'var(--color-surface-hover)',
                  cursor: 'pointer',
                  textAlign: 'center',
                  transition: 'border-color var(--transition-fast)'
                }}
              >
                <UploadSimple size={24} weight="bold" style={{ color: 'var(--color-text-muted)' }} />
                <div>
                  <span style={{ fontWeight: 600, textDecoration: 'underline' }}>Click to upload</span> or drag and drop
                  <div className="muted" style={{ fontSize: 'var(--text-xs)', marginTop: '2px' }}>
                    JPG, PNG, WEBP or PDF (max 5MB)
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
            ) : (
              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  padding: 'var(--space-3) var(--space-4)',
                  border: '1px solid var(--color-border)',
                  borderRadius: 'var(--radius-md)',
                  background: 'var(--color-surface)'
                }}
              >
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

          <button
            type="submit"
            className="btn btn-lg"
            disabled={submitting || loadingChapters}
            style={{ marginTop: 'var(--space-4)', width: '100%' }}
          >
            {submitting ? 'Creating account & uploading ID…' : 'Create account'}
          </button>
        </form>
      </div>

      <p className="muted text-center" style={{ marginTop: 'var(--space-6)' }}>
        Already have an account? <Link to="/login" style={{ fontWeight: 600 }}>Sign in</Link>
      </p>
    </div>
  )
}
