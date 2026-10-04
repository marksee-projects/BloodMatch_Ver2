import { useEffect, useState } from 'react'
import { api } from '../services/apiClient'
import SearchableCombobox from './ui/SearchableCombobox'

/**
 * Cascading Bataan location selector (municipality/city -> optional barangay).
 * Calls onChange({ location_id, municipality_code, barangay_code }) whenever the
 * resolved selection changes. Backend remains the authority for coordinates.
 */
export default function LocationSelector({
  municipalityId,
  municipalityCode,
  barangayCode,
  onChange,
  errors = {},
  municipalityLabel = 'Municipality / City',
  required = true
}) {
  const [municipalities, setMunicipalities] = useState([])
  const [barangays, setBarangays] = useState([])
  const [loadError, setLoadError] = useState(null)

  useEffect(() => {
    api
      .get('/api/locations/municipalities')
      .then((d) => setMunicipalities(d.municipalities || []))
      .catch((err) => setLoadError(err.message))
  }, [])

  useEffect(() => {
    if (!municipalityCode) {
      setBarangays([])
      return undefined
    }
    let active = true
    api
      .get(`/api/locations/barangays?municipality_code=${encodeURIComponent(municipalityCode)}`)
      .then((d) => {
        if (active) setBarangays(d.barangays || [])
      })
      .catch((err) => {
        if (active) setLoadError(err.message)
      })
    return () => {
      active = false
    }
  }, [municipalityCode])

  const handleMunicipality = (val) => {
    const muni = municipalities.find((m) => m.name.toLowerCase() === val.toLowerCase().trim())
    const code = muni ? muni.psgc_code : ''
    // Reset barangay: the previous selection may not belong here.
    onChange({
      location_id: muni ? muni.location_id : null,
      municipality_code: code || null,
      barangay_code: null
    })
  }

  const handleBarangay = (val) => {
    const brgy = barangays.find((b) => b.name.toLowerCase() === val.toLowerCase().trim())
    const code = brgy ? brgy.psgc_code : ''
    onChange({
      location_id: brgy ? brgy.location_id : (municipalities.find((m) => m.psgc_code === municipalityCode)?.location_id ?? null),
      municipality_code: municipalityCode,
      barangay_code: code || null
    })
  }

  return (
    <>
      <div className="field">
        <label htmlFor={municipalityId}>{municipalityLabel}{required ? ' *' : ''}</label>
        <SearchableCombobox
          id={municipalityId}
          value={municipalities.find(m => m.psgc_code === municipalityCode)?.name || ''}
          onChange={handleMunicipality}
          options={municipalities.map(m => ({ label: m.name, value: m.name }))}
          placeholder="Select Municipality / City..."
          required={required}
        />
        {loadError && <span className="field-error">{loadError}</span>}
        {errors.municipality_code && <span className="field-error">{errors.municipality_code.join(' ')}</span>}
        {errors.location_id && <span className="field-error">{errors.location_id.join(' ')}</span>}
      </div>

      <div className="field">
        <label htmlFor={`${municipalityId}-barangay`}>Barangay{required ? ' *' : ''}</label>
        <SearchableCombobox
          id={`${municipalityId}-barangay`}
          value={barangays.find(b => b.psgc_code === barangayCode)?.name || ''}
          onChange={handleBarangay}
          options={barangays.map(b => ({ label: b.name, value: b.name }))}
          placeholder="Select Barangay..."
          required={required}
        />
        {errors.barangay_code && <span className="field-error">{errors.barangay_code.join(' ')}</span>}
      </div>
    </>
  )
}
