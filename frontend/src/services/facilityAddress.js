export const emptyFacilityLocation = () => ({ location_id: null, municipality_code: null, barangay_code: null })
export const OTHER_LOCATION_NAME = 'Other donation venue'

export function findHospital(hospitals, name) {
  return hospitals.find(h => h.name.trim().toLowerCase() === name.trim().toLowerCase()) || null
}

// Every facility selection starts fresh; a municipal reference never guesses a barangay.
export function locationForHospital(hospital) {
  return hospital?.location ? {
    location_id: hospital.location.location_id,
    municipality_code: hospital.location.municipality_code,
    barangay_code: hospital.address_complete ? hospital.location.barangay_code : null
  } : emptyFacilityLocation()
}
