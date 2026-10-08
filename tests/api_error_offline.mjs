// Mocked fetch only: no real HTTP requests, database operations or email sends.
import assert from 'node:assert/strict'
import { api, clearCsrf } from '../frontend/src/services/apiClient.js'

const originalFetch = globalThis.fetch
let failure
const calls = []
globalThis.fetch = async (path, options = {}) => {
  calls.push(path)
  if (path === '/api/csrf') return Response.json({ data: { csrf_token: 'offline-token' } })
  return Response.json({ success: false, error: failure }, { status: 403 })
}
try {
  failure = { code: 'cooldown', message: 'Cooldown active.', eligible_again_at: '2099-01-01 00:00:00' }
  await assert.rejects(api.get('/api/offline-fixture'), error => {
    assert.equal(error.status, 403)
    assert.equal(error.code, 'cooldown')
    assert.equal(error.eligibleAgainAt, failure.eligible_again_at)
    assert.equal(error.message, failure.message)
    return true
  })
  failure = { message: 'Verify email.', details: { code: 'EMAIL_UNVERIFIED', email: ['Verify this address.'] } }
  await assert.rejects(api.get('/api/offline-fixture'), error => {
    assert.equal(error.code, 'EMAIL_UNVERIFIED')
    assert.deepEqual(error.details.email, ['Verify this address.'])
    return true
  })
  failure = { code: 'email_unverified', message: 'Verify email.', eligible_again_at: null }
  await assert.rejects(api.upload('/api/offline-upload', new Blob(['offline'])), error => {
    assert.equal(error.code, 'email_unverified')
    assert.equal(error.message, failure.message)
    return true
  })
  assert.deepEqual(calls, ['/api/offline-fixture', '/api/offline-fixture', '/api/csrf', '/api/offline-upload'])
  console.log('PASS 3 offline error checks: eligibility code/date, legacy details, upload errors; all fetches mocked.')
} finally {
  globalThis.fetch = originalFetch
  clearCsrf()
}
