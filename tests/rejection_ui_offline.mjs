// Offline render/callback checks. API is mocked; no DB, network or real email.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { normalizeRejectionReason, rejectionReasonError } from '../frontend/src/services/rejectionReason.js'

const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { build } = require('esbuild')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { MemoryRouter } = require('react-router-dom')
const frontend = fileURLToPath(new URL('../frontend/', import.meta.url))
const calls = []
globalThis.rejectionUiApi = {
  get: async () => ({ pending_reports: states[0] }),
  post: async (...args) => { calls.push(args); return { result: { decision: 'REJECTED' } } }
}
const result = await build({
  absWorkingDir: frontend, entryPoints: ['src/pages/officer/views/ConfirmationsView.jsx'],
  bundle: true, write: false, format: 'cjs', platform: 'node', packages: 'external', jsx: 'automatic',
  plugins: [{ name: 'offline-fixtures', setup(builder) {
    builder.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api = globalThis.rejectionUiApi', loader: 'js' }))
    builder.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default new Proxy({}, {get: (_, key) => key})', loader: 'js' }))
  } }]
})
const module = { exports: {} }
const icons = new Proxy({}, { get: () => () => React.createElement('svg', { 'aria-hidden': true }) })
new Function('require', 'module', 'exports', result.outputFiles[0].text)(id => id === '@phosphor-icons/react' ? icons : require(id), module, module.exports)
const View = module.exports.default
let passed = 0
const cases = JSON.parse(readFileSync(new URL('./fixtures/rejection_reason_cases.json', import.meta.url), 'utf8'))
for (const fixture of cases) {
  const input = 'input' in fixture ? fixture.input : fixture.repeat.repeat(fixture.count)
  assert.equal(!rejectionReasonError(input), fixture.valid, fixture.label)
  if (fixture.valid) assert.equal(normalizeRejectionReason(input), fixture.expected ?? input, fixture.label)
  passed++
}
const report = { id: 17, donor_name: 'Fixture donor', required_blood_type: 'O+', facility_name: 'Fixture clinic',
  reported_at: '2026-10-01 00:00:00', can_confirm: false, can_reject: true, request_status: 'CANCELLED', match_status: 'CLOSED' }
const states = [[report], null, null, null, null, '', null]
let active = false
let index = 0
let tree
const originalState = React.useState
const originalError = console.error
React.useState = initial => {
  if (!active) return originalState(initial)
  const i = index++
  const state = originalState(states[i])
  return [state[0], value => { states[i] = typeof value === 'function' ? value(states[i]) : value }]
}
console.error = (message, ...args) => {
  if (!String(message).includes('useLayoutEffect does nothing on the server')) originalError(message, ...args)
}
function Fixture() { active = true; index = 0; try { tree = View(); return tree } finally { active = false } }
function render() { return renderToStaticMarkup(React.createElement(MemoryRouter, null, React.createElement(Fixture))) }
function find(node, predicate) {
  if (Array.isArray(node)) return node.map(child => find(child, predicate)).find(Boolean)
  if (!node || typeof node !== 'object') return undefined
  return predicate(node) ? node : find(node.props?.children, predicate)
}
function text(node) {
  if (typeof node === 'string' || typeof node === 'number') return String(node)
  if (Array.isArray(node)) return node.map(text).join('')
  return node && typeof node === 'object' ? text(node.props?.children) : ''
}
function check(label, condition) { assert.ok(condition, label); passed++; console.log(`PASS ${label}`) }
try {
  let html = render()
  const confirm = find(tree, node => node.type === 'button' && text(node).includes('Confirm Completed Donation'))
  check('Closed report confirmation disabled while explicit rejection remains', confirm?.props.disabled === true && html.includes('Reject Report') && html.includes('reject it with a reason'))
  find(tree, node => node.type === 'button' && node.props.children === 'Reject Report').props.onClick()
  html = render()
  check('Reject opens required accessible reason dialog without posting', html.includes('role="alertdialog"') && html.includes('Rejection reason') && html.includes('required=""') && calls.length === 0)
  let dialog = find(tree, node => node.props?.confirmLabel === 'Reject report')
  check('Blank reason blocks confirmation', dialog.props.confirmDisabled === true)
  dialog.props.onCancel(); render()
  check('Cancel rejection preserves report and sends nothing', calls.length === 0 && states[4] === null)
  states[4] = report; states[5] = 'a'.repeat(501); html = render()
  dialog = find(tree, node => node.props?.confirmLabel === 'Reject report')
  check('Overlength reason blocked with clear feedback', dialog.props.confirmDisabled && html.includes('at most 500 characters'))
  states[5] = '  Could not verify this report.  '; render()
  dialog = find(tree, node => node.props?.confirmLabel === 'Reject report')
  dialog.props.onConfirm(); dialog.props.onConfirm()
  await new Promise(resolve => setImmediate(resolve))
  check('Valid rejection posts trimmed reason once', calls.length === 1 && calls[0][0] === '/api/officer/donation-reports/17/reject'
    && calls[0][1].rejection_reason === 'Could not verify this report.')
  check('Successful rejection closes dialog', states[4] === null)
  states[0] = [{ ...report, can_reject: false }]; states[1] = null; states[4] = null; html = render()
  check('Self-review actions disabled', html.includes('You cannot review your own report.')
    && find(tree, node => node.type === 'button' && node.props.children === 'Reject Report').props.disabled)
  console.log(`${passed} offline rejection validation/UI checks passed; API mocked.`)
} finally {
  React.useState = originalState; console.error = originalError; delete globalThis.rejectionUiApi
}
