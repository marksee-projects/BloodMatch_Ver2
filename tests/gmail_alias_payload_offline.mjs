import assert from 'node:assert/strict'
import { registrationPayload, registrationFormData } from '../frontend/src/services/registrationPayload.js'
const form = { first_name:'Alias',middle_name:'',last_name:'Fixture',email:' MarkSeeJr+Requester2@gmail.com ',password:'OfflineFixture123',chapter_id:'1',date_of_birth:'1990-01-02',blood_type:'',phone:'' }
const payload = registrationPayload(form,{ location_id:null })
assert.equal(payload.email,'markseejr+requester2@gmail.com')
assert.notEqual(payload.email,'markseejr@gmail.com')
assert.equal(registrationFormData(payload,new Blob(['fixture'])).get('email'),'markseejr+requester2@gmail.com')
console.log('PASS 3 offline alias JSON/multipart payload checks; no registration or network.')
