// Render actual components and exercise controls with synthetic transport only.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFileSync, existsSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { parseAdminDashboard } from '../frontend/src/services/adminDashboard.js'
import { reportEventLabel, reportSnapshot, requesterResponseLabel } from '../frontend/src/services/reportPresentation.js'
import { notificationDisplayText, notificationDeepLink } from '../frontend/src/services/notifications.js'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { build } = require('esbuild')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const root = fileURLToPath(new URL('../frontend/', import.meta.url))
const overview = { total_users: 22, total_members: 21, total_staff: 1, active_open_requests: 8, open_units_needed: 12, fulfilled_requests: 2, cancelled_requests: 2, expired_requests: 16, resolved_requests: 20, fulfillment_rate_percent: 10, cancellation_rate_percent: 10, expiration_rate_percent: 80, pending_verifications: 2, pending_donation_reports: 1, enrolled_donors: 15, available_donors: 10 }
const pool = { total_enrolled: 15, available: 10, standby: 3, cooldown: 1, unavailable: 1 }
const dashboard = { overview, donor_pool: pool, recent_system_activity: [] }
assert.equal(parseAdminDashboard(dashboard), dashboard)
for (const bad of [undefined, null, '0', NaN, -1]) assert.throws(() => parseAdminDashboard({ ...dashboard, overview: { ...overview, active_open_requests: bad } }))
assert.throws(() => parseAdminDashboard({ ...dashboard, overview: { ...overview, fulfillment_rate_percent: 101 } }))
assert.equal(parseAdminDashboard({ ...dashboard, overview: { ...overview, active_open_requests: 0 } }).overview.active_open_requests, 0)
assert.deepEqual(reportSnapshot('{broken'), {})
assert.deepEqual(reportSnapshot('null'), {})
assert.equal(reportSnapshot('{"occurred_at":"2026-10-20 00:00:00"}').occurred_at, '2026-10-20 00:00:00')
assert.equal(reportEventLabel('mutually_acknowledged'), 'Donation mutually acknowledged')
assert.equal(reportEventLabel('unknown_internal_event'), 'Report updated')
assert.equal(requesterResponseLabel('received'), 'Donation received')
const notice = { related_type: 'donation_report', related_id: 85, title: 'Donation report #85 completed' }
assert.equal(notificationDisplayText(notice, 'title'), 'Donation report completed')
assert.equal(notificationDeepLink(notice, 'member'), '/donation-reports?report=85')

const fixture = { role: 'admin', queryKeys: [], queryFns: [], calls: [], reports: [{ id: 2, donor_id: 8, requester_id: 9, donor_name: 'Synthetic donor', requester_name: 'Synthetic requester', facility_name: 'Synthetic facility', status: 'CONFIRMED', request_status: 'FULFILLED', completion_basis: 'mutual', occurred_at: '2026-10-19 00:00:00', reported_at: '2026-10-19 01:00:00', content_version: 1 }], error: null }
const offlineApi = { get: async path => { fixture.calls.push(path); return path.endsWith('/proof') ? { evidence: [] } : { corrections: [] } }, post: () => { throw new Error('No submissions in this test') } }
const queries = {
  useQuery: options => { fixture.queryKeys.push(options.queryKey); fixture.queryFns.push(options.queryFn); return { data: options.queryKey[0] === 'admin-dashboard' ? dashboard : { reports: fixture.reports, total: 21 }, isPending: false, isFetching: false, error: fixture.error, refetch: async () => {} } },
  useQueryClient: () => ({ invalidateQueries: async () => {} }),
}
async function component(entry, exportName = 'default') {
  const result = await build({ absWorkingDir: root, entryPoints: [entry], bundle: true, write: false, platform: 'node', format: 'cjs', packages: 'external', jsx: 'automatic', plugins: [{ name: 'offline-redesign', setup(b) {
    b.onResolve({ filter: /.*/ }, args => {
      if (!args.path.startsWith('.') && !isAbsolute(args.path)) return { path: args.path, external: true }
      const path = resolve(args.importer ? dirname(args.importer) : root, args.path)
      return { path: [path, path + '.jsx', path + '.js'].find(existsSync), namespace: 'source' }
    })
    b.onLoad({ filter: /AuthContext\.jsx$/ }, () => ({ contents: 'export const useAuth=()=>({user:{id:3,role:fixture.role},refresh:async()=>{}})', loader: 'js' }))
    b.onLoad({ filter: /NotificationContext\.jsx$/ }, () => ({ contents: 'export const useNotify=()=>()=>{}', loader: 'js' }))
    b.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api=offlineApi', loader: 'js' }))
    b.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default new Proxy({}, {get:(_,key)=>key})', loader: 'js' }))
    b.onLoad({ filter: /.*/, namespace: 'source' }, args => ({ contents: readFileSync(args.path, 'utf8').replace('function ReportWorkspace(', 'export function ReportWorkspace('), loader: extname(args.path) === '.jsx' ? 'jsx' : 'js' }))
  } }] })
  const mod = { exports: {} }
  const icons = new Proxy({}, { get: () => () => React.createElement('svg') })
  const router = { useLocation: () => ({ pathname: '/donation-reports', search: '', state: null }), Link: ({ to, children, ...props }) => React.createElement('a', { href: to, ...props }, children) }
  new Function('require', 'module', 'exports', 'fixture', 'offlineApi', result.outputFiles[0].text)(id => id === '@phosphor-icons/react' ? icons : id === '@tanstack/react-query' ? queries : id === 'react-router-dom' ? router : require(id), mod, mod.exports, fixture, offlineApi)
  return mod.exports[exportName]
}
const Dashboard = await component('src/pages/admin/views/DashboardView.jsx')
let html = renderToStaticMarkup(React.createElement(Dashboard))
assert(html.includes('10%') && html.includes('80%') && html.includes('2 of 20 resolved requests'))
assert(html.includes('12 units needed') && html.includes('Unavailable'))
assert(!html.includes('0% Verified') && !html.includes('Verified Donors:'))
assert(html.includes('/admin/verifications') && html.includes('/admin/confirmations'))

const originalState = React.useState
let active = false, index = 0, states = [], tree, current, props = {}
React.useState = initial => {
  const actual = originalState(initial)
  if (!active) return actual
  const position = index++
  if (!(position in states)) states[position] = typeof initial === 'function' ? initial() : initial
  return [states[position], next => { states[position] = typeof next === 'function' ? next(states[position]) : next }]
}
function Render() { active = true; index = 0; try { tree = current(props); return tree } finally { active = false } }
const render = () => renderToStaticMarkup(React.createElement(Render))
const walk = (node, predicate) => Array.isArray(node) ? node.map(n => walk(n, predicate)).find(Boolean) : !node || typeof node !== 'object' ? null : predicate(node) ? node : walk(node.props?.children, predicate)
const control = text => walk(tree, n => n.props?.children === text)
try {
  current = await component('src/components/ReportDisclosure.jsx')
  props = { label: 'View event details', hideLabel: 'Hide event details', children: React.createElement('p', null, 'Private event evidence') }
  html = render(); assert(html.includes('aria-expanded="false"') && !html.includes('Private event evidence'))
  walk(tree, n => n.props?.['aria-expanded'] === false).props.onClick()
  html = render(); assert(html.includes('aria-expanded="true"') && html.includes('Private event evidence') && html.includes('Hide event details'))

  current = await component('src/components/DonationReportInbox.jsx', 'ReportWorkspace'); props = { admin: true }; states = []
  html = render(); assert(html.includes('Needs attention') && html.includes('Completed donations'))
  assert(!html.includes('Donation report #') && !html.includes('Report version') && !html.includes('>Refresh<'))
  control('Completed donations').props.onClick()
  html = render(); assert(html.includes('Find a completed donation') && html.includes('Page 1 of 2'))
  assert.equal(fixture.queryKeys.at(-1)[3], 'history')
  // Exercise the exact query function without enabling network.
  await fixture.queryFns.at(-1)(); assert(fixture.calls.at(-1).includes('scope=history') && fixture.calls.at(-1).includes('page_size=20'))
  control('Next').props.onClick(); render(); assert.equal(fixture.queryKeys.at(-1)[4], 2)
  fixture.role = 'member'; props = { admin: false }; states = []
  html = render(); assert(html.includes('href="/"') && html.includes('Back') && !html.includes('Needs attention'))

  current = await component('src/components/DonationProofPanel.jsx'); props = { report: fixture.reports[0], participant: true, canReview: false }; states = []
  render(); walk(tree, n => n.props?.['aria-expanded'] === false).props.onClick(); await new Promise(resolve => setImmediate(resolve))
  html = render(); assert(html.includes('Private supporting photos') && html.includes('Request correction') && !html.includes('<textarea'))
  control('Request correction').props.onClick(); html = render()
  assert(html.includes('<textarea') && html.includes('Submit correction request') && html.includes('does not automatically reverse'))
} finally { React.useState = originalState }
console.log('PASS dashboard contract/rendering, report scopes/pagination, ID presentation, disclosure, correction-form and notification-link checks; synthetic transport only.')
