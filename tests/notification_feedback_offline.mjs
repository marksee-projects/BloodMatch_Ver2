import assert from 'node:assert/strict'
import { requestSavedNotice } from '../frontend/src/services/requestSavedNotice.js'
const saved = { request: { id: 42, urgency: 'routine' }, matching_status: 'completed' }
assert.equal(requestSavedNotice(saved).severity, 'success')
assert.equal(requestSavedNotice(saved).action.to, '/requests/42/matches')
assert.equal(requestSavedNotice(saved, true).title, 'Request updated')
const emergency = requestSavedNotice({ ...saved, request: { id: 42, urgency: 'emergency' } })
assert.equal(emergency.severity, 'success')
assert.equal(emergency.title, 'Emergency request posted')
const failed = requestSavedNotice({ ...saved, matching_status: 'failed_retry_required' })
assert.equal(failed.severity, 'warning')
assert.match(failed.message, /Do not post a duplicate/)
assert.match(failed.title, /saved/)
assert.equal(requestSavedNotice({}, false, 'emergency').title, 'Emergency request posted')
console.log('PASS 9 request-result notification checks; no network or database.')
