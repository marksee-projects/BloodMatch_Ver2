// Static render checks only: no browser, backend, database, seeds or network.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

process.env.NODE_ENV = 'production'
const frontend = fileURLToPath(new URL('../frontend/', import.meta.url))
process.chdir(frontend)
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { build } = require('esbuild')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { MemoryRouter, Route, Routes } = require('react-router-dom')
const { QueryClient, QueryClientProvider } = require('@tanstack/react-query')
const h = React.createElement

async function loadPage(name) {
  const result = await build({
    absWorkingDir: frontend, entryPoints: [`./src/pages/${name}.jsx`],
    bundle: true, write: false, format: 'cjs', platform: 'node', packages: 'external', jsx: 'automatic',
    plugins: [{ name: 'static-fixtures', setup(builder) {
      builder.onResolve({ filter: /.*/ }, (args) => {
        if (!args.path.startsWith('.') && !isAbsolute(args.path)) return { path: args.path, external: true }
        const path = resolve(args.importer ? dirname(args.importer) : frontend, args.path)
        const found = [path, `${path}.jsx`, `${path}.js`].find(existsSync)
        if (!found) throw new Error(`Missing source: ${path}`)
        return { path: found, namespace: 'source-fixture' }
      })
      builder.onLoad({ filter: /AuthContext\.[jt]sx?$/ }, () => ({ contents: 'export const useAuth = () => ({ user: globalThis.requestUiUser, refresh: () => {} })', loader: 'js' }))
      builder.onLoad({ filter: /RequestCreationContext\.jsx$/ }, () => ({ contents: 'export const useRequestCreation = () => () => {}', loader: 'js' }))
      builder.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api = new Proxy({}, { get: () => () => { throw new Error("Network forbidden in static checks") } })', loader: 'js' }))
      builder.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default new Proxy({}, {get: (_, key) => key})', loader: 'js' }))
      builder.onLoad({ filter: /.*/, namespace: 'source-fixture' }, (args) => ({ contents: readFileSync(args.path, 'utf8'), loader: extname(args.path) === '.jsx' ? 'jsx' : 'js' }))
    } }]
  })
  const module = { exports: {} }
  // Icons are decorative here; their package's CJS entry is incompatible with Node 26.
  const icons = new Proxy({}, { get: () => (props) => h('svg', { 'aria-label': props['aria-label'], 'aria-hidden': props['aria-hidden'] }) })
  const fixtureRequire = (id) => id === '@phosphor-icons/react' ? icons : require(id)
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(fixtureRequire, module, module.exports)
  return module.exports.default
}

const MatchesPage = await loadPage('MatchesPage')
const ProfilePage = await loadPage('ProfilePage')
const ConfirmationDialog = await loadPage('../components/ConfirmationDialog')
const request = { id: 45, requester_id: 7, requester_name: 'Requester', requester_chapter_name: 'Chapter', requester_verification_status: 'verified', can_view_requester_profile: true,
  required_blood_type: 'B-', quantity_units: 2, facility_name: 'A hospital with a long name for the compact selector', status: 'OPEN', urgency: 'urgent',
  needed_datetime: '2099-10-15 09:00:00', created_at: '2026-10-06 13:00:00', location: { municipality_name: 'Bagac' }, match_count: 1, response_count: 0 }
const donor = { match_id: 81, display_name: 'Eligible donor', chapter_name: 'Donor chapter', availability: 'available', approximate_distance_km: 17.9, status: 'NOTIFIED', profile_user_id: 9 }
let passed = 0
function check(label, fn) { fn(); passed++; console.log(`PASS ${label}`) }

function render(Page, path, requests, data, user = { id: 7, role: 'member' }, profile, previousQueries = []) {
  globalThis.requestUiUser = user
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } })
  for (const [key, value] of previousQueries) client.setQueryData(key, value)
  client.setQueryData(['my-requests', user.id], requests)
  if (data) client.setQueryData(['request-matches', path.split('/')[2], user.id], data)
  if (profile) client.setQueryData(['profile', user.id], profile)
  const markup = renderToStaticMarkup(h(QueryClientProvider, { client }, h(MemoryRouter, { initialEntries: [path] },
    h(Routes, null, h(Route, { path: path === '/matches' ? '/matches' : path === '/profile' ? '/profile' : '/requests/:id/matches', element: h(Page) })))))
  client.clear()
  return markup
}
const ownData = { viewer_mode: 'requester', request, matches: [donor] }
const one = render(MatchesPage, '/requests/45/matches', [request], ownData)
check('Single request has no selector and no self-profile button', () => {
  assert.ok(!one.includes('<select')); assert.ok(!one.includes('href="/profile/7"'))
})
check('Donor profile, ranking, availability, proximity and notification status survive', () => {
  for (const value of ['href="/profile/9"', 'Eligible donor', '>1</div>', 'available', 'About 17.9 km', 'NOTIFIED', 'Blood needed', request.facility_name]) assert.ok(one.includes(value), value)
})
const second = { ...request, id: 46, required_blood_type: 'A-', facility_name: 'Another hospital' }
const multiple = render(MatchesPage, '/requests/45/matches', [request, second], ownData)
check('Multiple requests have compact labels and real URL values without visible request IDs', () => {
  assert.ok(multiple.includes('<select')); assert.ok(multiple.includes('value="45"')); assert.ok(multiple.includes('value="46"'))
  const labels = [...multiple.matchAll(/<option[^>]*>(.*?)<\/option>/g)].map((match) => match[1])
  assert.equal(labels.length, 2); assert.ok(labels.every((label) => !label.includes('Request #') && !label.includes('45') && !label.includes('46')))
  assert.ok(labels[0].includes('…')); assert.ok(labels[1].includes('Another hospital'))
})
check('No active requests shows the honest empty state and Create action', () => {
  const html = render(MatchesPage, '/matches', [], null)
  assert.ok(html.includes('active blood request yet')); assert.ok(html.includes('Create request')); assert.ok(!html.includes('Eligible donor'))
})
check('Closed request deep link retains its summary, donors and selection alongside active requests', () => {
  const closed = { ...request, status: 'FULFILLED' }
  const html = render(MatchesPage, '/requests/45/matches', [second], { ...ownData, request: closed })
  assert.ok(html.includes('FULFILLED')); assert.ok(html.includes('value="45"')); assert.ok(html.includes('value="46"')); assert.ok(html.includes('Eligible donor'))
})
check('Staff viewing another requester retains requester profile access without a personal selector', () => {
  const html = render(MatchesPage, '/requests/45/matches', [second], ownData, { id: 99, role: 'officer' })
  assert.ok(html.includes('href="/profile/7"')); assert.ok(!html.includes('<select'))
})
check('Donor view retains help, profile and return-to-Home actions', () => {
  const html = render(MatchesPage, '/requests/45/matches', [], { viewer_mode: 'donor', request, matches: [{ ...donor, is_current_user: true }] }, { id: 9, role: 'member' })
  assert.ok(html.includes('I can help')); assert.ok(html.includes('href="/profile/7"')); assert.ok(html.includes('Compatible requests')); assert.ok(!html.includes('<select'))
})
const profile = { id: 7, full_name: 'Requester', first_name: 'Requester', chapter_name: 'Chapter', role: 'member', blood_type: 'A-', verification_status: 'verified', donor_enrolled: true, availability: 'available', availability_window: {}, capabilities: { create_request: true }, documents: [] }
const ownProfile = render(ProfilePage, '/profile', [], null, undefined, { profile, reports: [], requests: [request, { ...second, status: 'CANCELLED' }] })
check('Profile Overview preserves closed requests, filters/counts, section Create action and Matches management', () => {
  for (const value of ['Filter blood requests by status', 'Fulfilled', 'Cancelled', 'Expired', 'Another hospital', 'potential donor', 'responses', 'Donation History', 'Verification', 'href="/matches"']) assert.ok(ownProfile.includes(value), value)
  assert.equal((ownProfile.match(/Create request/g) || []).length, 1)
  assert.ok(!ownProfile.includes('Set unavailable'))
  assert.ok(ownProfile.indexOf('>Edit</button>') < ownProfile.indexOf('>View matches</a>'))
  assert.ok(ownProfile.indexOf('>View matches</a>') < ownProfile.indexOf('>Cancel request</button>'))
})
check('Admin Profile does not display a previous member cache while loading', () => {
  const memberData = { profile: { ...profile, full_name: 'Previous member' }, requests: [request], reports: [] }
  const html = render(ProfilePage, '/profile', [], null, { id: 99, role: 'admin' }, null,
    [[['profile'], memberData], [['profile', profile.id], memberData]])
  assert.ok(html.includes('Loading profile')); assert.ok(!html.includes('Previous member')); assert.ok(!html.includes(request.facility_name))
})
check('Admin Matches ignores the previous member request selector cache', () => {
  const html = render(MatchesPage, '/matches', [], null, { id: 99, role: 'admin' }, null,
    [[['my-requests'], [request]], [['my-requests', profile.id], [request]]])
  assert.ok(html.includes('active blood request yet')); assert.ok(!html.includes(request.facility_name))
})
check('Viewer-specific match cache cannot expose a prior requester donor list', () => {
  const html = render(MatchesPage, '/requests/45/matches', [], null, { id: 99, role: 'admin' }, null,
    [[['request-matches', '45'], ownData], [['request-matches', '45', profile.id], ownData]])
  assert.ok(html.includes('Loading request')); assert.ok(!html.includes('Eligible donor')); assert.ok(!html.includes('href="/profile/9"'))
})
check('Existing form fields, create/edit API distinction and legacy routes remain in source', () => {
  const source = readFileSync(`${frontend}src/pages/RequestFormPage.jsx`, 'utf8')
  for (const field of ['required_blood_type', 'quantity_units', 'facility_name', 'location_id', 'hospital_id', 'urgency', 'needed_datetime', 'EMAIL_UNVERIFIED']) assert.ok(source.includes(field), field)
  assert.ok(source.includes('api.put(`/api/requests/${id}`')); assert.ok(source.includes("api.post('/api/requests'"))
  const app = readFileSync(`${frontend}src/App.jsx`, 'utf8')
  for (const route of ['/requests/mine', '/requests/new', '/requests/:id/matches', '/matches']) assert.ok(app.includes(`path="${route}"`), route)
  assert.ok(app.includes('<Navigate to="/profile" replace />'))
})
check('Save and discard confirmations expose clear, accessible choices', () => {
  for (const [title, confirmLabel] of [['Save these changes?', 'Save changes'], ['Discard your changes?', 'Discard changes']]) {
    const html = renderToStaticMarkup(h(ConfirmationDialog, { open: true, title, confirmLabel, onConfirm: () => {}, onCancel: () => {} }, h('p', null, 'Review your request changes.')))
    assert.ok(html.includes('role="alertdialog"')); assert.ok(html.includes('aria-modal="true"'))
    assert.ok(html.includes(title)); assert.ok(html.includes(confirmLabel)); assert.ok(html.includes('Keep editing'))
    assert.ok(html.includes('aria-labelledby=')); assert.ok(html.includes('aria-describedby='))
  }
  assert.equal(renderToStaticMarkup(h(ConfirmationDialog, { open: false })), '')
})
check('Pending cancellation disables both popup actions; failure remains visible for retry', () => {
  const props = { open: true, title: 'Cancel this blood request?', confirmLabel: 'Cancel request', cancelLabel: 'Keep request', destructive: true, onConfirm: () => {}, onCancel: () => {} }
  const pending = renderToStaticMarkup(h(ConfirmationDialog, { ...props, busy: true }, h('p', null, 'The request will stop accepting responses.')))
  assert.ok(pending.includes('aria-busy="true"')); assert.equal((pending.match(/disabled=""/g) || []).length, 2)
  const failed = renderToStaticMarkup(h(ConfirmationDialog, { ...props, error: 'Unable to cancel. Try again.' }))
  assert.ok(failed.includes('role="alert"')); assert.ok(failed.includes('Unable to cancel. Try again.')); assert.ok(!failed.includes('disabled=""'))
})
console.log(`${passed} static checks passed. Browser interactions and backend behavior were not exercised.`)
