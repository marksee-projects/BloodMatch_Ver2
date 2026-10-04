import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Check, WarningCircle } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import styles from './RegisterPage.module.css'
import LocationSelector from '../components/LocationSelector'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import SearchableCombobox from '../components/ui/SearchableCombobox'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const URGENCIES = [
  { id: 'routine', label: 'Routine (Scheduled surgeries / standard transfusions)' },
  { id: 'urgent', label: 'Urgent (Required within 24 hours)' },
  { id: 'critical', label: 'Critical (Immediate emergency / trauma)' }
]

export default function RequestFormPage({ id, onSuccess, onCancel }) {
  const editing = Boolean(id)

  const [form, setForm] = useState({
    required_blood_type: '',
    quantity_units: 1,
    facility_name: '',
    urgency: 'routine',
    needed_date: '',
    needed_time: ''
  })
  const [hospitalsList, setHospitalsList] = useState([])
  const [location, setLocation] = useState({ location_id: null, municipality_code: null, barangay_code: null })
  const [errors, setErrors] = useState({}); const [stepErrors, setStepErrors] = useState({});
  const [message, setMessage] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [loaded, setLoaded] = useState(!editing)

  useEffect(() => {
    api.get('/api/hospitals')
      .then((data) => setHospitalsList(data.hospitals || []))
      .catch((err) => console.error('Failed to load hospitals:', err))

    if (!editing) return
    api
      .get(`/api/requests/${id}`)
      .then((data) => {
        const r = data.request
        setForm({
          required_blood_type: r.required_blood_type,
          quantity_units: r.quantity_units,
          facility_name: r.facility_name,
          urgency: r.urgency,
          needed_date: (r.needed_datetime || '').slice(0, 10),
          needed_time: (r.needed_datetime || '').slice(11, 16)
        })
        const loc = r.location
        setLocation({
          location_id: loc?.location_id ?? null,
          municipality_code: loc?.municipality_code ?? null,
          barangay_code: loc?.barangay_code ?? null
        })
        setLoaded(true)
      })
      .catch((err) => setMessage(err.message))
  }, [editing, id])

  const setField = (name) => (e) => {
    setForm((f) => ({ ...f, [name]: e.target.value }))
    if (stepErrors[name]) setStepErrors((prev) => { const next = { ...prev }; delete next[name]; return next; })
  }

  const onSubmit = async (e) => {
    e.preventDefault()
    setErrors({})
    setMessage(null)
    setSubmitting(true)
    try {
      const payload = { ...form, location_id: location.location_id }
      
      const matchedHospital = hospitalsList.find(
        (h) => h.name.toLowerCase().trim() === payload.facility_name.toLowerCase().trim()
      )
      payload.hospital_id = matchedHospital ? matchedHospital.id : null

      if (payload.needed_date && payload.needed_time) payload.needed_datetime = `${payload.needed_date}T${payload.needed_time}`
      delete payload.needed_date; delete payload.needed_time
      
      if (editing) {
        await api.put(`/api/requests/${id}`, payload)
      } else {
        await api.post('/api/requests', payload)
      }
      if (onSuccess) onSuccess()
    } catch (err) {
      if (err.details && Object.keys(err.details).length > 0) {
        setErrors(err.details)
      } else {
        setMessage(err.message || 'Failed to save request.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const [step, setStep] = useState(1)

  const validateStep = (currentStep) => {
    const newErrors = {}
    if (currentStep === 1) {
      if (!form.required_blood_type) newErrors.required_blood_type = ['Please select a blood type.']
      if (!form.quantity_units || form.quantity_units < 1 || form.quantity_units > 10) newErrors.quantity_units = ['Quantity must be between 1 and 10.']
    }
    if (currentStep === 2) {
      if (!form.facility_name || !form.facility_name.trim()) {
        newErrors.facility_name = ['Facility name is required.']
      }
      if (!location.municipality_code) {
        newErrors.municipality_code = ['Municipality is required.']
      }
      if (!location.barangay_code) {
        newErrors.barangay_code = ['Barangay is required.']
      }
    }
    if (currentStep === 3) {
      if (!form.needed_date) newErrors.needed_datetime = ['Date needed is required.']
      if (!form.needed_time) newErrors.needed_datetime = ['Time needed is required.']
    }
    setStepErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const nextStep = () => { if (validateStep(step)) setStep((s) => Math.min(s + 1, 3)) }
  const prevStep = () => setStep((s) => Math.max(s - 1, 1))

  if (!loaded) {
    return (
      <div style={{ maxWidth: '640px', margin: '0 auto', width: '100%' }}>
        <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
          <LoadingSpinner text="Loading request details…" />
        </div>
      </div>
    )
  }

  const stepPercentage = step === 1 ? '0%' : step === 2 ? '50%' : '100%'

  const hospitalOptions = hospitalsList.map(h => ({ value: h.name, label: h.name }))

  return (
      <div style={{ maxWidth: '640px', margin: '0 auto', width: '100%' }}>
      <div style={{ marginBottom: 'var(--space-6)', textAlign: 'center' }}>
        <h1 style={{ marginBottom: '0' }}>
          {editing ? `Edit Blood Request #${id}` : 'Create Blood Request'}
        </h1>
      </div>

      {message && (
        <div className="alert alert-error" role="alert">
          <WarningCircle size={20} weight="fill" color="var(--color-danger)" style={{ flexShrink: 0, marginTop: '2px' }} />
          <span>{message}</span>
        </div>
      )}

      <div className={styles.wizardCard}>
        <div className={styles.stepperContainer}>
          <div className={styles.stepperTrack}>
            <div className={styles.stepperLineBg} />
            <div className={styles.stepperLineFill} style={{ width: `calc(${stepPercentage} * 0.88)` }} />

            <button
              type="button"
              className={`${styles.stepItem} ${step === 1 ? styles.stepActive : step > 1 ? styles.stepCompleted : ''}`}
              onClick={() => step > 1 && setStep(1)}
              aria-label="Step 1: Requirements"
            >
              <div className={styles.stepBubble}>
                {step > 1 ? <Check size={18} weight="bold" /> : '1'}
              </div>
              <span className={styles.stepLabel}>Requirements</span>
            </button>

            <button
              type="button"
              className={`${styles.stepItem} ${step === 2 ? styles.stepActive : step > 2 ? styles.stepCompleted : ''}`}
              onClick={() => {
                if (step > 2) setStep(2)
                else if (step === 1 && validateStep(1)) setStep(2)
              }}
              aria-label="Step 2: Facility & Location"
            >
              <div className={styles.stepBubble}>
                {step > 2 ? <Check size={18} weight="bold" /> : '2'}
              </div>
              <span className={styles.stepLabel}>Facility &amp; Location</span>
            </button>

            <button
              type="button"
              className={`${styles.stepItem} ${step === 3 ? styles.stepActive : ''}`}
              onClick={() => {
                if (validateStep(1) && validateStep(2)) setStep(3)
              }}
              aria-label="Step 3: Timing & Urgency"
            >
              <div className={styles.stepBubble}>3</div>
              <span className={styles.stepLabel}>Timing &amp; Urgency</span>
            </button>
          </div>
        </div>

        <form className="form" onSubmit={onSubmit} noValidate>
          
          {step === 1 && (
            <div className="form-step">
              
              <div className="field">
                <label htmlFor="required_blood_type">Required Blood Type *</label>
                <select
                  id="required_blood_type"
                  value={form.required_blood_type}
                  onChange={setField('required_blood_type')}
                  required
                >
                  <option value="">Select blood type…</option>
                  {BLOOD_TYPES.map((t) => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
                {(stepErrors.required_blood_type || errors.required_blood_type) && <span className="field-error">{(stepErrors.required_blood_type || errors.required_blood_type).join(' ')}</span>}
              </div>

              <div className="field">
                <label htmlFor="quantity_units">Quantity Needed (Units) *</label>
                <input
                  id="quantity_units"
                  type="number"
                  min={1}
                  max={10}
                  value={form.quantity_units}
                  onChange={setField('quantity_units')}
                  required
                />

                {(stepErrors.quantity_units || errors.quantity_units) && <span className="field-error">{(stepErrors.quantity_units || errors.quantity_units).join(' ')}</span>}
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="form-step">
              
              <div className="field">
                <label htmlFor="facility_name">Healthcare Facility / Hospital Name *</label>
                <SearchableCombobox
                  id="facility_name"
                  options={hospitalOptions}
                  placeholder="Select or type facility name..."
                  value={form.facility_name}
                  onChange={(val) => {
                    setField('facility_name')({ target: { value: val } })
                  }}
                  required={true}
                />
                {(stepErrors.facility_name || errors.facility_name) && <span className="field-error">{(stepErrors.facility_name || errors.facility_name).join(' ')}</span>}
              </div>

              <div className="field" role="group" aria-labelledby="request-location-heading">
                <span id="request-location-heading" className="metric-label" style={{ display: 'block', marginBottom: 'var(--space-2)' }}>Facility Location</span>

                <LocationSelector
                  municipalityId="request-municipality"
                  municipalityCode={location.municipality_code}
                  barangayCode={location.barangay_code}
                  onChange={setLocation}
                  errors={stepErrors}
                  required={true}
                />
              </div>
            </div>
          )}

          {step === 3 && (
            <div className="form-step">
              
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--space-4)' }}>
                <div className="field">
                  <label htmlFor="needed_date">Date *</label>

                <input
                  id="needed_date"
                  type="date"
                  value={form.needed_date}
                  onChange={setField('needed_date')}
                />

                </div>
                <div className="field">
                  <label htmlFor="needed_time">Time *</label>
                  <input
                    id="needed_time"
                    type="time"
                    value={form.needed_time}
                    onChange={setField('needed_time')}
                  />
                </div>
              </div>
              {errors.needed_datetime && (
                <div className="alert alert-error" style={{ marginBottom: 'var(--space-4)' }}>
                  <WarningCircle size={20} weight="fill" color="var(--color-danger)" style={{ flexShrink: 0, marginTop: '2px' }} />
                  <span>{errors.needed_datetime.join(' ')}</span>
                </div>
              )}

              <div className="field">
                <label htmlFor="urgency">Urgency Level *</label>
                <select id="urgency" value={form.urgency} onChange={setField('urgency')} required>
                  {URGENCIES.map((u) => (
                    <option key={u.id} value={u.id}>{u.label}</option>
                  ))}
                </select>
                {errors.urgency && <span className="field-error">{errors.urgency.join(' ')}</span>}
              </div>
            </div>
          )}

          <div className="button-group" style={{ marginTop: 'var(--space-4)', paddingTop: 'var(--space-4)', borderTop: '1px solid var(--color-border-hairline)', display: 'flex', justifyContent: 'space-between' }}>
            <button type="button" className="btn btn-ghost" onClick={onCancel}>
              Cancel
            </button>

            <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
              {step > 1 && (
                <button type="button" className="btn btn-secondary" onClick={prevStep}>
                  Back
                </button>
              )}
              
              {step < 3 ? (
                <button type="button" className="btn" onClick={nextStep}>
                  Next Step
                </button>
              ) : (
                <button type="submit" className="btn" disabled={submitting}>
                  {submitting ? 'Saving…' : (editing ? 'Update Request' : 'Publish Request')}
                </button>
              )}
            </div>
          </div>
        </form>
      </div>
    </div>
  )
}
