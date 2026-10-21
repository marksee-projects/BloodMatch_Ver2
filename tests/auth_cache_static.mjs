// Offline regression checks for the real AuthProvider callbacks. No API/DB calls.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'

process.env.NODE_ENV = 'production'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const React = require('react')
const { QueryClient } = require('@tanstack/react-query')
const { transform } = require('esbuild')
const source = readFileSync(new URL('../frontend/src/context/AuthContext.jsx', import.meta.url), 'utf8')
const compiled = await transform(source, { loader: 'jsx', jsx: 'automatic', format: 'cjs' })
const client = new QueryClient({ defaultOptions: { queries: { staleTime: 300000, retry: false } } })
const slots = []
let cursor = 0
let csrfClears = 0
let response
const effects = []
const listeners = new Map()
const timers = new Map()
const originalSetInterval = globalThis.setInterval
const originalClearInterval = globalThis.clearInterval
globalThis.setInterval = (fn, delay) => { const key = timers.size + 1; timers.set(key, { fn, delay }); return key }
globalThis.clearInterval = key => timers.delete(key)
globalThis.window = { addEventListener: (name, fn) => listeners.set(name, fn), removeEventListener: (name) => listeners.delete(name) }
globalThis.document = { visibilityState: "visible", addEventListener() {}, removeEventListener() {} }
Object.defineProperty(globalThis, "localStorage", { configurable: true, value: { setItem() {} } })
const hooks = {
  ...React,
  useState(initial) {
    const index = cursor++
    if (!(index in slots)) slots[index] = initial
    return [slots[index], (value) => { slots[index] = typeof value === 'function' ? value(slots[index]) : value }]
  },
  useRef(initial) {
    const index = cursor++
    if (!(index in slots)) slots[index] = { current: initial }
    return slots[index]
  },
  useCallback: (fn) => fn,
  useEffect: (effect) => { effects.push(effect) }
}
const api = { get: (...args) => response(...args), post: (...args) => response(...args) }
const fixtureRequire = (name) => {
  if (name === 'react') return hooks
  if (name === '@tanstack/react-query') return { useQueryClient: () => client }
  if (name === '../services/apiClient') return { api, clearCsrf: () => { csrfClears++ } }
  return require(name)
}
const module = { exports: {} }
new Function('require', 'module', 'exports', compiled.code)(fixtureRequire, module, module.exports)
function render() { cursor = 0; return module.exports.AuthProvider({ children: null }) }
function auth() { return render().props.value }
function deferred() { let resolve; const promise = new Promise((done) => { resolve = done }); return { promise, resolve } }
const member = { id: 7, role: 'member' }
const admin = { id: 99, role: 'admin' }
response = async () => ({ user: member })
await auth().login('fixture-member', 'fixture-password')
client.setQueryData(['profile', member.id], { profile: member })
client.setQueryData(['my-requests', member.id], [{ id: 45 }])
await auth().refresh()
assert.equal(client.getQueryData(['profile', member.id]).profile.id, member.id)
console.log('PASS Same-account refresh preserves its cache')

const oldRefresh = deferred()
response = () => oldRefresh.promise
const pendingRefresh = auth().refresh()
const lateQuery = deferred()
const pendingQuery = client.fetchQuery({ queryKey: ['request-matches', '45', member.id], queryFn: () => lateQuery.promise }).catch(() => null)
response = async () => ({ user: admin })
await auth().login('fixture-admin', 'fixture-password')
assert.equal(auth().user.id, admin.id)
assert.equal(client.getQueryCache().getAll().length, 0)
assert.equal(render().props.children.key, String(admin.id))
console.log('PASS Account switch clears previous data and resets the private component subtree')

oldRefresh.resolve({ user: member })
lateQuery.resolve({ viewer_mode: 'requester', request: { requester_id: member.id } })
await Promise.all([pendingRefresh, pendingQuery])
assert.equal(auth().user.id, admin.id)
assert.equal(client.getQueryCache().getAll().length, 0)
console.log('PASS Late member refresh/query results cannot restore the previous session or cache')

client.setQueryData(['profile', admin.id], { profile: admin })
response = async () => { throw new Error('Fixture login rejected') }
await assert.rejects(auth().login('fixture-invalid', 'fixture-password'))
assert.equal(auth().user.id, admin.id)
assert.ok(client.getQueryData(['profile', admin.id]))
console.log('PASS Failed login preserves the existing signed-in account')

await assert.rejects(auth().logout())
assert.equal(auth().user, null)
assert.equal(client.getQueryCache().getAll().length, 0)
assert.equal(csrfClears, 1)
console.log('PASS Logout clears identity, cache and CSRF even if its API call fails')

response = async () => ({ user: member })
await auth().login('fixture-member', 'fixture-password')
client.setQueryData(['profile', member.id], { profile: member })
response = async () => { throw Object.assign(new Error('Fixture session expired'), { status: 401 }) }
await auth().refresh()
assert.equal(auth().user, null)
assert.equal(client.getQueryCache().getAll().length, 0)
console.log('PASS Session expiry clears private cached data')
response = async () => ({ user: member })
const cleanup = effects[0]()
await Promise.resolve()
client.setQueryData(['profile', member.id], { profile: member })
const otherTab = deferred()
response = () => otherTab.promise
listeners.get('storage')({ key: 'bloodmatch-session-change' })
assert.equal(auth().user, null)
assert.equal(auth().loading, true)
assert.equal(client.getQueryCache().getAll().length, 0)
otherTab.resolve({ user: admin })
await Promise.resolve()
assert.equal(auth().user.id, admin.id)
assert.equal(auth().loading, false)
console.log('PASS Another tab changing accounts clears identity and cache before reloading the session')
response = async () => ({ user: member })
listeners.get('focus')()
await Promise.resolve()
assert.equal(auth().user.id, member.id)
console.log('PASS Returning to the tab revalidates the shared session')
const first = deferred()
response = () => first.promise
const firstRefresh = auth().refresh()
response = async () => ({ user: admin })
await auth().refresh()
first.resolve({ user: member })
await firstRefresh
assert.equal(auth().user.id, admin.id)
console.log('PASS Older refresh results cannot overwrite a newer session refresh')
const poll = [...timers.values()][0]
assert.equal(poll.delay, 5000)
client.setQueryData(['profile', admin.id], { profile: admin })
response = async () => { throw new TypeError('Fixture connection lost') }
await poll.fn()
assert.equal(auth().user.id, admin.id)
assert.ok(client.getQueryData(['profile', admin.id]))
console.log('PASS Background checks preserve identity during temporary network failures')
let backgroundCalls = 0
response = async () => { backgroundCalls++; throw Object.assign(new Error('Account deactivated'), { status: 403 }) }
document.visibilityState = 'hidden'
await poll.fn()
assert.equal(backgroundCalls, 0)
document.visibilityState = 'visible'
await poll.fn()
assert.equal(auth().user, null)
assert.equal(client.getQueryCache().getAll().length, 0)
console.log('PASS Deactivated account is signed out by visible-tab polling without a page refresh')
await poll.fn()
assert.equal(backgroundCalls, 1, 'signed-out tabs stop checking')
cleanup()
assert.equal(listeners.size, 0)
assert.equal(timers.size, 0)
globalThis.setInterval = originalSetInterval
globalThis.clearInterval = originalClearInterval
client.clear()
console.log('12 offline authentication checks passed. Browser sessions and backend behavior were not exercised.')
