// Actual TanStack Query subscriptions with a fake clock; no network/database writes.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { transformSync } = require('esbuild')
const root = new URL('../frontend/src/', import.meta.url).pathname.replace(/^\/(\w:)/, '$1')
const original = require.extensions['.js']
require.extensions['.js'] = (module, path) => path.replaceAll('\\', '/').startsWith(root)
  ? module._compile(transformSync(readFileSync(path, 'utf8'), { format: 'cjs', loader: 'js' }).code, path)
  : original(module, path)
const { QueryClient, QueryObserver, environmentManager, timeoutManager, focusManager, onlineManager } = require('@tanstack/react-query')
const { chapterDemandOptions, canReadDemand } = require('./src/services/chapterDemand.js')
const { api } = require('./src/services/apiClient.js')
let now = 1000, nextId = 0
const jobs = new Map()
const schedule = (fn, delay, interval = false) => { const id = ++nextId; jobs.set(id, { fn, at: now + delay, delay, interval }); return id }
timeoutManager.setTimeoutProvider({
  setTimeout: (fn, ms) => schedule(fn, ms), clearTimeout: id => jobs.delete(id),
  setInterval: (fn, ms) => schedule(fn, ms, true), clearInterval: id => jobs.delete(id),
})
environmentManager.setIsServer(() => false)
focusManager.setFocused(true); onlineManager.setOnline(true)
const flush = async () => { for (let i = 0; i < 12; i++) await Promise.resolve() }
const tick = async ms => {
  const end = now + ms
  for (;;) {
    const entry = [...jobs].filter(([, job]) => job.at <= end).sort((a,b) => a[1].at-b[1].at)[0]
    if (!entry) break
    const [id, job] = entry; now = job.at
    if (job.interval) job.at += job.delay; else jobs.delete(id)
    job.fn(); await flush()
  }
  now = end; await flush()
}
let calls = [], pending = []
api.get = (url, { signal }) => new Promise((resolve, reject) => {
  calls.push({ url, signal }); pending.push({ resolve: chapters => resolve({ chapters }), reject })
  signal.addEventListener('abort', () => reject(Object.assign(new Error('aborted'), { name: 'AbortError' })))
})
const user = { id: 1, role: 'member', account_status: 'active' }
const client = new QueryClient({ defaultOptions: { queries: { gcTime: Infinity } } }); client.mount()
const options = chapterDemandOptions(user)
const observer = new QueryObserver(client, options)
const stop = observer.subscribe(() => {})
assert.equal(calls.length, 1, 'mount fetches immediately')
const second = new QueryObserver(client, options), stopSecond = second.subscribe(() => {})
assert.equal(calls.length, 1, 'duplicate observers share the active request')
observer.refetch({ cancelRefetch: false }); second.refetch({ cancelRefetch: false })
assert.equal(calls.length, 1, 'manual refresh coalesces')
pending.shift().resolve([{ chapter_id: 1, open_requests_count: 2 }]); await flush()
await tick(29999); assert.equal(calls.length, 1)
await tick(1); assert.equal(calls.length, 2, 'one fetch at the 30-second interval')
assert.equal(observer.getCurrentResult().data[0].open_requests_count, 2, 'background fetch keeps chart')
pending.shift().reject(new Error('offline failure')); await flush()
assert.equal(observer.getCurrentResult().data[0].open_requests_count, 2, 'failure retains successful chart')
assert(observer.getCurrentResult().dataUpdatedAt > 0)
focusManager.setFocused(false); await tick(60000)
assert.equal(calls.length, 2, 'hidden tab does not poll')
focusManager.setFocused(true); await flush()
assert.equal(calls.length, 3, 'visibility resume fetches immediately')
pending.shift().resolve([]); await flush()
onlineManager.setOnline(false); await tick(60000)
assert.equal(calls.length, 3, 'offline does not start requests')
onlineManager.setOnline(true); await flush()
assert.equal(calls.length, 4, 'reconnect fetches immediately')
pending.shift().reject(Object.assign(new Error('Forbidden'), { status: 403 })); await flush()
await tick(90000); assert.equal(calls.length, 4, '403 stops polling')
focusManager.setFocused(false); focusManager.setFocused(true); await flush()
assert.equal(calls.length, 4, '403 cannot bypass revalidation on focus')
client.resetQueries({ queryKey: options.queryKey, exact: true }); await flush()
assert.equal(calls.length, 5, 'session revalidation reset permits fetching')
pending.shift().resolve([]); await flush()
stopSecond()
const filtered = chapterDemandOptions(user, { bloodType: 'A+', urgency: 'urgent', days: '7' })
observer.setOptions(filtered); await flush()
assert.equal(observer.getCurrentResult().data, undefined, 'new filters never show old filter data')
assert(calls.at(-1).url.includes('blood_type=A%2B&urgency=urgent&days=7'))
const oldCall = calls.at(-1), oldPending = pending.shift()
observer.setOptions(chapterDemandOptions({ ...user, id: 2 })); await flush()
assert(oldCall.signal.aborted, 'filter/account switch aborts the unobserved request')
oldPending.resolve([{ chapter_id: 99 }]); await flush()
assert.equal(observer.getCurrentResult().data, undefined, 'late previous response does not restore old data')
pending.shift().resolve([{ chapter_id: 2 }]); await flush()
assert.equal(observer.getCurrentResult().data[0].chapter_id, 2)
stop(); const afterStop = calls.length
await tick(60000); assert.equal(calls.length, afterStop, 'unmount removes polling')
assert(!canReadDemand(null)); assert(!canReadDemand({ ...user, account_status: 'inactive' }))
assert(canReadDemand({ ...user, verification_status: 'pending' }), 'no invented membership-verification gate')
client.unmount(); client.clear(); jobs.clear()
console.log('PASS: immediate/30s fetch, deduplication, retained data, hidden/offline/resume, access-error stop/revalidation, filter isolation, abort/race, account switch, unmount.')
