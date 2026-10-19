import assert from 'node:assert/strict'
import { donationReportForm, donationTimeToUtc, donationTimeForPicker } from '../frontend/src/services/donationCompletion.js'
import { loadDocumentPreview } from '../frontend/src/services/documentPreview.js'
const photo = new Blob(['synthetic pixels'], { type: 'image/png' })
const form = donationReportForm(42, '2026-10-19T13:45', [photo])
assert.equal(form.get('match_id'), '42')
assert.equal(form.get('occurred_at'), '2026-10-19T05:45:00Z')
assert.equal(form.getAll('proofs[]').length, 1)
assert.equal(form.has('note'), false)
assert.equal(donationReportForm(42, '2026-10-19T13:45').getAll('proofs[]').length, 0)
assert.throws(() => donationReportForm(42, '2026-02-30T13:45'))
assert.equal(donationTimeToUtc('2026-10-19T13:45:17'), '2026-10-19T05:45:17Z')
assert.equal(donationTimeForPicker('2026-10-19 05:45:17'), '2026-10-19T13:45:17')
let called
await loadDocumentPreview({ id: 5, doc_type: 'donation_proof', mime_type: 'image/png' }, undefined, async (path, options) => {
  called = { path, options }
  return { ok: true, headers: new Headers({ 'content-type': 'image/png' }), blob: async () => photo }
})
assert.equal(called.path, '/api/donation-proof/5/file')
assert.equal(called.options.cache, 'no-store')
assert.equal(called.options.credentials, 'same-origin')
await assert.rejects(loadDocumentPreview({ id: 5, doc_type: 'donation_proof', mime_type: 'image/png' }, undefined, async () => ({ ok: false })))
console.log('PASS 12 offer/proof UI checks; synthetic blobs/fake fetch, no API/database/files/SMTP.')
