import assert from 'node:assert/strict'
import { registrationPayload, registrationFormData } from '../frontend/src/services/registrationPayload.js'
import { notificationDisplayText, notificationDeepLink } from '../frontend/src/services/notifications.js'
const form = { first_name:' Alex ',middle_name:'',last_name:' Member ',email:' ALEX@example.test ',password:'Example123',chapter_id:'1',date_of_birth:'1990-01-02',blood_type:'A+',phone:'' }
const location = { location_id:42,municipality_code:'030809000',barangay_code:'030809023' }
const payload = registrationPayload(form,location)
assert.equal(payload.location_id,42)
assert.equal(payload.municipality_code,location.municipality_code)
assert.equal(payload.barangay_code,location.barangay_code)
const data = registrationFormData(payload,new Blob(['fixture']))
for (const field of ['location_id','municipality_code','barangay_code']) assert.equal(data.get(field),String(payload[field]))
assert.equal(data.get('national_id').size,7)
const paired = registrationFormData(payload, new Blob(['front'], { type: 'application/pdf' }), new Blob(['back'], { type: 'image/png' }))
assert.equal(paired.get('national_id_front').size, 5)
assert.equal(paired.get('national_id_back').size, 4)
assert.equal(paired.get('national_id_front').type, 'application/pdf')
assert.equal(paired.get('national_id_back').type, 'image/png')
assert(!paired.has('national_id'))
assert(!registrationFormData(payload).has('national_id'))
assert(!('location_id' in registrationPayload(form,{ location_id:null })))
assert(!registrationFormData(registrationPayload(form,{ ...location,barangay_code:null }),new Blob()).has('barangay_code'))
const legacy = { related_type:'blood_request',related_id:123,title:'New compatible donation opportunity (#123)',body:'Blood request #123 is cancelled.',read_at:'2026-10-10 00:00:00' }
const before = JSON.stringify(legacy)
assert.equal(notificationDisplayText(legacy,'title'),'New compatible donation opportunity')
assert.equal(notificationDisplayText(legacy,'body'),'Blood request is cancelled.')
assert.equal(notificationDeepLink(legacy),'/requests/123/matches')
assert.equal(JSON.stringify(legacy),before)
assert.equal(notificationDisplayText({ related_type:'blood_request',body:"Alex Member's blood request needs donors." },'body'),"Alex Member's blood request needs donors.")
assert.equal(notificationDisplayText({ related_type:'donation_report',body:'Report #123 awaiting review.' },'body'),'Report #123 awaiting review.')
assert.equal(notificationDisplayText({ related_type:'donation_report',body:'Donation report for blood request #123 awaits review.' },'body'),'Donation report for blood request awaits review.')
console.log('PASS 22 offline registration payload/notification checks; no API, database or mail.')
