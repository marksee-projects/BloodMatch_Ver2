import assert from 'node:assert/strict'
import { notificationDeepLink, notificationDisplayText } from '../frontend/src/services/notifications.js'

assert.equal(notificationDeepLink({type:'verification.requested',related_id:89},'admin'),'/admin/verifications?user=89')
assert.equal(notificationDeepLink({type:'donation.reported'},'admin'),'/admin/confirmations')
for (const role of ['member','officer']) {
  assert.equal(notificationDeepLink({type:'verification.requested',related_id:89},role),null)
  assert.equal(notificationDeepLink({type:'donation.reported'},role),null)
}
assert.equal(notificationDeepLink({related_type:'blood_request',related_id:12},'member'),'/requests/12/matches')
assert.equal(notificationDeepLink({type:'verification.decision'},'member'),'/profile')
assert.equal(notificationDisplayText({related_type:'blood_request',body:'Blood request #123 updated'},'body'),'Blood request updated')
console.log('PASS CP0 canonical notification destinations and legacy-display preservation; no API/database.')
