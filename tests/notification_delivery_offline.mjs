import assert from 'node:assert/strict'
import { liveNotificationKind, notificationInstant, canClaimNotification, claimLiveNotification } from '../frontend/src/services/notificationDelivery.js'
import { notificationDeepLink } from '../frontend/src/services/notifications.js'
const now = Date.parse('2026-10-19T10:00:00Z')
const fresh = { id: 1, type: 'offer.accepted', created_at: '2026-10-19 09:59:50', related_type: 'blood_request', related_id: 91 }
const options = { baseline: now - 30000, now }
let checks = 0
function check(a,b) { assert.deepEqual(a,b); checks++ }
check(notificationInstant('2026-10-19 09:59:50'), now-10000)
check(notificationDeepLink({ ...fresh, related_conversation_id:24 }),'/chat?conversation=24&request=91')
check(liveNotificationKind(fresh,options),'action')
check(liveNotificationKind(fresh,{ ...options, baseline: now }),null)
check(liveNotificationKind(fresh,{ ...options, baseline: null }),null)
check(liveNotificationKind(fresh,{ ...options, now: NaN }),null)
check(liveNotificationKind(fresh,{ ...options, viewing: true }),null)
check(liveNotificationKind({ ...fresh, created_at: '2026-10-19 09:58:00' },{ ...options, baseline: now-300000 }),null)
check(liveNotificationKind({ ...fresh, created_at: '2026-10-19 10:01:00' },options),null)
check(liveNotificationKind({ ...fresh, read_at: fresh.created_at },options),null)
for (const type of ['request.cancelled','request.expired','request.fulfilled','match.responded','verification.decision','donation.reminder','donation.escalated','donation.confirmed','offer.coordination']) check(liveNotificationKind({ ...fresh,type },options),null)
for (const type of ['offer.donation_prompt','donation.awaiting_acknowledgment','donation.clarification_requested']) check(liveNotificationKind({ ...fresh,type },options),'action')
const emergency={ ...fresh, type:'match.new', related_request_urgency:'emergency', related_request_status:'OPEN' }
check(liveNotificationKind(emergency,options),'emergency')
check(liveNotificationKind({ ...emergency, related_request_status:'CANCELLED' },options),null)
check(liveNotificationKind({ ...emergency, related_request_urgency:'urgent' },options),null)
check(canClaimNotification({ at: now-1000, kind:'action', ids:['1'] },1,'emergency',now),false)
check(canClaimNotification({ at: now-1000, kind:'action' },2,'action',now),false)
check(canClaimNotification({ at: now-30000, kind:'action' },2,'action',now),true)
check(canClaimNotification({ at: now-1000, kind:'action' },2,'emergency',now),true)
check(canClaimNotification({ at: now-1000, kind:'emergency' },2,'emergency',now),false)
const memory=new Map()
globalThis.localStorage={ getItem:k=>memory.get(k)||null,setItem:(k,v)=>memory.set(k,v) }
// Deterministically serialize competing tab claims using an in-memory Web Locks stand-in.
let tail=Promise.resolve()
Object.defineProperty(globalThis,'navigator',{ configurable:true,value:{ locks:{ request:(_key,fn)=>{const result=tail.then(fn);tail=result.catch(()=>{});return result} } } })
check(await Promise.all([claimLiveNotification(1,7,'action'),claimLiveNotification(1,7,'action')]),[true,false])
check(await claimLiveNotification(2,7,'action'),true)
check(await claimLiveNotification(1,8,'action'),false)
check(await claimLiveNotification(1,9,'emergency'),true)
check([...memory.values()].every(v=>!v.includes('blood_request')&&!v.includes('91')),true)
Object.defineProperty(globalThis,'navigator',{ configurable:true,value:{} })
check(await claimLiveNotification(1,10,'action'),false)
console.log(`${checks} notification-delivery checks passed; no HTTP, database or real browser storage.`)
