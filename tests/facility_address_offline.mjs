import assert from 'node:assert/strict'
import { findHospital, locationForHospital } from '../frontend/src/services/facilityAddress.js'

const complete = { id: 1, name: 'Saved hospital', address_complete: true, location: { location_id: 20, municipality_code: 'balanga', barangay_code: 'saved' } }
const incomplete = { id: 2, name: 'Municipal hospital', address_complete: false, location: { location_id: 10, municipality_code: 'mariveles', barangay_code: null } }
const missing = { id: 3, name: 'Unmapped hospital', address_complete: false, location: null }
const hospitals = [complete, incomplete, missing]
assert.equal(findHospital(hospitals, ' SAVED HOSPITAL '), complete)
let location = locationForHospital(complete)
assert.deepEqual(location, { location_id: 20, municipality_code: 'balanga', barangay_code: 'saved' })
location = locationForHospital(findHospital(hospitals, 'Municipal hospital'))
assert.deepEqual(location, { location_id: 10, municipality_code: 'mariveles', barangay_code: null })
location = { ...location, location_id: 11, barangay_code: 'manual' }
location = locationForHospital(findHospital(hospitals, 'Unlisted clinic'))
assert.deepEqual(location, { location_id: null, municipality_code: null, barangay_code: null })
assert.deepEqual(locationForHospital(missing), location)
assert.deepEqual(locationForHospital(complete), { location_id: 20, municipality_code: 'balanga', barangay_code: 'saved' })
console.log('6 facility selection checks passed (pure state transitions; no browser).')
