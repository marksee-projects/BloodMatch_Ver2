// Execute the real floating-tab effects with fake transport and browser events.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { transformSync } = require('esbuild')
const React = require('react')
const slots = [], effects = [], pending = [], intervals = new Map(), listeners = new Map()
let cursor = 0, effectIndex = 0, refreshes = 0, failRead = true
let previewMode = false, serverUnread = 2
const posts = [], gets = []
const hooks = { ...React,
  useState(initial) { const i = cursor++; if (!(i in slots)) slots[i] = initial; return [slots[i], value => { slots[i] = typeof value === 'function' ? value(slots[i]) : value }] },
  useRef(initial) { const i = cursor++; return slots[i] ||= { current: initial } },
  useCallback(fn, deps) { const i = cursor++; const old = slots[i]; if (!old || deps.some((d, n) => !Object.is(d, old.deps[n]))) slots[i] = { fn, deps }; return slots[i].fn },
  useEffect(fn, deps) { const i = effectIndex++; const old = effects[i]; if (!old || deps.some((d, n) => !Object.is(d, old.deps[n]))) pending.push(() => { old?.cleanup?.(); effects[i] = { deps, cleanup: fn() } }) },
}
const fakeApi = {
  async get(path) { gets.push(path); return previewMode ? { conversations: [{ id: 24, unread_count: serverUnread, recipient: { id: 2 }, last_message: { id: 7, sender_id: 2, body: 'Unread fixture' } }] } : { messages: path.includes('after_id=') ? [] : [{ id: 7, sender_id: 2, body: 'Unread fixture' }] } },
  async post(path, body) { posts.push({ path, body }); if (failRead) { failRead = false; throw new Error('Synthetic transient failure') } if (previewMode) serverUnread = 0; return {} },
}
function loadComponent(name) {
const source = readFileSync(new URL(`../frontend/src/components/chat/${name}.jsx`, import.meta.url), 'utf8')
const code = transformSync(source, { loader: 'jsx', jsx: 'automatic', format: 'cjs' }).code
const mod = { exports: {} }
new Function('require', 'module', 'exports', code)(id => {
  if (id === 'react') return hooks
  if (id === 'react/jsx-runtime') return require(id)
  if (id.endsWith('apiClient')) return { api: fakeApi }
  if (id.endsWith('AuthContext')) return { useAuth: () => ({ user: { id: 1 } }) }
  if (id.endsWith('ChatContext')) return { useChat: () => ({ fetchUnreadCount: refreshUnread, unreadCount: serverUnread, canChat: true }) }
  if (id.endsWith('NotificationContext')) return { useNotify: () => () => {}, useActionNoticeState: () => hooks.useState(null) }
  if (id.endsWith('.css')) return { default: {} }
  if (id === '@phosphor-icons/react') return new Proxy({}, { get: () => () => null })
  if (id === 'react-router-dom') return { Link: () => null }
  return () => null
}, mod, mod.exports)
return mod.exports.default
}
let Component = loadComponent('FloatingChatTab')
function refreshUnread() { refreshes++ }
const originals = Object.fromEntries(['window', 'document', 'setInterval', 'clearInterval', 'setTimeout'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]))
const events = {
  addEventListener(name, fn) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(fn) },
  removeEventListener(name, fn) { listeners.get(name)?.delete(fn) },
}
const tab = { conversationId: 24, recipient: { id: 2 }, isMinimized: true }
function render() { cursor = 0; effectIndex = 0; const tree = Component({ tab, onClose() {}, onToggleMinimize() {} }); pending.splice(0).forEach(fn => fn()); return tree }
const tick = () => new Promise(resolve => setImmediate(resolve))
try {
  globalThis.document = { hidden: false, ...events }
  globalThis.window = { ...events, matchMedia: () => ({ matches: false }) }
  globalThis.setInterval = (fn, delay) => { const key = Symbol(); intervals.set(key, { fn, delay }); return key }
  globalThis.clearInterval = key => intervals.delete(key)
  globalThis.setTimeout = () => 0
  render(); await tick(); render()
  assert.equal(posts.length, 0, 'Loading a minimized tab does not read messages')
  document.hidden = true; tab.isMinimized = false; render(); await tick()
  assert.equal(posts.length, 0, 'A hidden page cannot mark a reopened tab read')
  document.hidden = false
  for (const fn of listeners.get('visibilitychange') || []) fn()
  await tick()
  assert.equal(posts.length, 1)
  assert.equal(refreshes, 0, 'Failed read does not claim that unread count was cleared')
  await [...intervals.values()].find(timer => timer.delay === 3000).fn(); await tick()
  assert(gets.at(-1).includes('after_id=7'))
  assert.equal(posts.length, 2, 'An empty poll retries previously fetched unread messages')
  assert.deepEqual(posts.at(-1), { path: '/api/chat/conversations/24/read', body: { last_message_id: 7 } })
  assert.equal(refreshes, 1, 'Successful read refreshes the server unread count')
  tab.isMinimized = true; render(); tab.isMinimized = false; render(); await tick()
  assert.equal(posts.length, 2, 'Reopening already-read messages does not repeatedly mark them read')
  effects.forEach(effect => effect?.cleanup?.()); effects.length = 0; slots.length = 0; pending.length = 0
  previewMode = true; Component = loadComponent('MessengerFlyout')
  window.setInterval = globalThis.setInterval; window.clearInterval = globalThis.clearInterval
  function find(node, predicate) { if (Array.isArray(node)) return node.map(child => find(child, predicate)).find(Boolean); if (!node || typeof node !== 'object') return null; return predicate(node) ? node : find(node.props?.children, predicate) }
  const trigger = tree => find(tree, node => node.type === 'button' && node.props.title === 'Chats')
  let tree = render()
  assert.equal(trigger(tree).props['aria-label'], 'Chats, 2 unread')
  trigger(tree).props.onClick(); render(); await tick(); tree = render()
  assert.equal(serverUnread, 0, 'Opening previews acknowledges their unread messages on the server')
  assert.equal(posts.at(-1).body.last_message_id, 7, 'Preview acknowledgment is bounded to the fetched last message')
  trigger(tree).props.onClick(); tree = render()
  assert.equal(trigger(tree).props['aria-expanded'], false)
  assert.equal(trigger(tree).props['aria-label'], 'Chats', 'Closing the dropdown does not restore the acknowledged unread badge')
  serverUnread = 1; tree = render()
  assert.equal(trigger(tree).props['aria-label'], 'Chats, 1 unread', 'A subsequent new unread message restores the badge')
  console.log('PASS floating-chat reopen, hidden/minimized guards, failed-read retry, fetched-message boundary and unread refresh; fake transport only.')
} finally {
  effects.forEach(effect => effect?.cleanup?.())
  for (const [key, descriptor] of Object.entries(originals)) {
    if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key]
  }
}
