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
  needed_datetime: '2099-10-15 09:00:00', created_at: '2026-10-06 13:00:00', location: { municipality_name: 'Bagac' }, match_count: 1, response_count: 0, can_respond: true }
const donor = { match_id: 81, display_name: 'Eligible donor', chapter_name: 'Donor chapter', availability: 'available', approximate_distance_km: 17.9, status: 'NOTIFIED', profile_user_id: 9 }
let passed = 0
function check(label, fn) { if (process.env.REQUEST_UI_CHECK && !new RegExp(process.env.REQUEST_UI_CHECK).test(label)) return; fn(); passed++; console.log(`PASS ${label}`) }
function offerButton(html) {
  return [...html.matchAll(/<button\b[^>]*>[\s\S]*?<\/button>/g)].map(match => match[0]).find(button => button.includes('I can help'))
}

function render(Page, path, requests, data, user = { id: 7, role: 'member' }, profile, previousQueries = []) {
  globalThis.requestUiUser = user
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } })
  for (const [key, value] of previousQueries) client.setQueryData(key, value)
  client.setQueryData(['my-requests', user.id], requests)
  if (data) client.setQueryData(['request-matches', path.split('/')[2], user.id], data)
  if (profile) client.setQueryData(['profile', user.id], profile)
  const markup = renderToStaticMarkup(h(QueryClientProvider, { client }, h(MemoryRouter, { initialEntries: [path] },
    h(Routes, null, h(Route, { path: path === '/matches' ? '/matches' : path === '/profile' ? '/profile' : path.startsWith('/profile/') ? '/profile/:id' : '/requests/:id/matches', element: h(Page) })))))
  client.clear()
  return markup
}
const ownData = { viewer_mode: 'requester', request, matches: [donor] }
const one = render(MatchesPage, '/requests/45/matches', [request], ownData)
check('Single request has no selector and no self-profile button', () => {
  assert.ok(!one.includes('id="matches-request"')); assert.ok(!one.includes('href="/profile/7"'))
})
check('Donor profile, ranking, availability, proximity and notification status survive', () => {
  for (const value of ['href="/profile/9"', 'Eligible donor', '>1</div>', 'available', 'About 17.9 km', 'NOTIFIED', 'Blood needed', request.facility_name]) assert.ok(one.includes(value), value)
})
const second = { ...request, id: 46, required_blood_type: 'A-', facility_name: 'Another hospital' }
const multiple = render(MatchesPage, '/requests/45/matches', [request, second], ownData)
check('Multiple requests have compact labels and real URL values without visible request IDs', () => {
  assert.ok(multiple.includes('<select')); assert.ok(multiple.includes('value="45"')); assert.ok(multiple.includes('value="46"'))
  const requestSelect = multiple.match(/<select[^>]*id="matches-request"[^>]*>(.*?)<\/select>/)?.[1] || ''
  const labels = [...requestSelect.matchAll(/<option[^>]*>(.*?)<\/option>/g)].map((match) => match[1])
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
  assert.ok(html.includes('href="/profile/7"')); assert.ok(!html.includes('id="matches-request"'))
})
check('Requester donor filters render with result count and preserve returned ordering', () => {
  for (const text of ['Search donors', 'Response status', 'All statuses', 'Awaiting response', 'All chapters', 'Reset', 'Showing 1 of 1 donors']) assert.ok(one.includes(text), text)
  const entries = [{ ...donor, match_id: 10, display_name: 'First donor', status: 'NOTIFIED' }, { ...donor, match_id: 11, display_name: 'Responding donor', status: 'RESPONDED' }]
  const html = render(MatchesPage, '/requests/45/matches', [request], { ...ownData, matches: entries })
  assert.ok(html.indexOf('First donor') < html.indexOf('Responding donor'))
})
check('Combined filters and filtered empty state render without changing source matches', () => {
  const originalState = React.useState
  const entries = [{ ...donor, match_id: 10, display_name: 'First donor', status: 'NOTIFIED', chapter_id: 1 }, { ...donor, match_id: 11, display_name: 'Responding donor', status: 'RESPONDED', chapter_id: 2 }]
  let filters = { search: 'responding', status: 'RESPONDED', chapter: '2' }
  React.useState = initial => {
    const state = originalState(initial)
    return initial && typeof initial === 'object' && 'search' in initial && 'chapter' in initial ? [filters, state[1]] : state
  }
  try {
    let html = render(MatchesPage, '/requests/45/matches', [request], { ...ownData, matches: entries })
    assert.ok(html.includes('Responding donor') && !html.includes('First donor') && html.includes('Showing 1 of 2 donors'))
    filters = { ...filters, search: 'no such donor' }
    html = render(MatchesPage, '/requests/45/matches', [request], { ...ownData, matches: entries })
    assert.ok(html.includes('No donors match your filters') && html.includes('Showing 0 of 2 donors'))
    assert.equal(entries.length, 2)
  } finally { React.useState = originalState }
})
check('Donor view retains help, profile and return-to-Home actions', () => {
  const html = render(MatchesPage, '/requests/45/matches', [], { viewer_mode: 'donor', request, matches: [{ ...donor, is_current_user: true }] }, { id: 9, role: 'member' })
  assert.ok(html.includes('I can help')); assert.ok(html.includes('href="/profile/7"')); assert.ok(html.includes('Compatible requests')); assert.ok(!html.includes('<select'))
})
check('Eligible compatible browser can offer without a persisted match', () => {
  const html = render(MatchesPage, '/requests/45/matches', [], { viewer_mode: 'browser', request, matches: [] }, { id: 9, role: 'member' })
  assert.ok(offerButton(html))
  assert.doesNotMatch(offerButton(html), /^<button[^>]*disabled/)
  assert.ok(!html.includes('Review donor eligibility'))
})
check('Stale NOTIFIED match obeys server eligibility and shows its reason/date', () => {
  const blocked = { ...request, can_respond: false, reason_code: 'cooldown', reason_text: 'Donation cooldown is active.', eligible_again_at: '2099-10-01 00:00:00' }
  const html = render(MatchesPage, '/requests/45/matches', [], { viewer_mode: 'donor', request: blocked, matches: [{ ...donor, is_current_user: true }] }, { id: 9, role: 'member' })
  assert.match(offerButton(html), /^<button[^>]*disabled/)
  assert.ok(html.includes('Donation cooldown is active.') && html.includes('Eligible again:'))
})
check('Staff request browsing is read-only and does not show donor setup advice', () => {
  const html = render(MatchesPage, '/requests/45/matches', [], { viewer_mode: 'browser', request: { ...request, can_respond: false, reason_code: 'staff_account' }, matches: [] }, { id: 99, role: 'officer' })
  assert.match(offerButton(html), /^<button[^>]*disabled/)
  assert.ok(!html.includes('Your blood type can support this request') && !html.includes('Review donor eligibility'))
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
check('Own rejected history displays escaped reason and honest legacy fallback', () => {
  const originalState = React.useState
  React.useState = initial => { const state = originalState(initial); return initial === 'overview' ? ['history', state[1]] : state }
  try {
    const reports = [
      { id: 1, required_blood_type: 'O+', facility_name: 'History clinic', reported_at: '2026-10-01 00:00:00', status: 'REJECTED', rejection_reason: '<script>alert(1)</script>' },
      { id: 2, required_blood_type: 'O+', facility_name: 'Legacy clinic', reported_at: '2026-10-01 00:00:00', status: 'REJECTED', rejection_reason: null }
    ]
    const html = render(ProfilePage, '/profile', [], null, undefined, { profile, reports, requests: [] })
    assert.ok(html.includes('Rejection reason:') && html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'))
    assert.ok(html.includes('No reason recorded') && !html.includes('<script>alert'))
  } finally { React.useState = originalState }
})
check('Other member profile does not expose rejection history', () => {
  const otherData = { profile: { ...profile, id: 88, full_name: 'Other member' }, reports: [{ status: 'REJECTED', rejection_reason: 'PRIVATE-REJECTION-REASON' }], requests: [] }
  const html = render(ProfilePage, '/profile/88', [], null, undefined, null, [[['member-profile', '88', 7], otherData]])
  assert.ok(html.includes('Other member'))
  assert.ok(!html.includes('mailto:') && !html.includes(profile.email), 'Other-member profile hides email')
  assert.ok(!html.includes('PRIVATE-REJECTION-REASON') && !html.includes('Rejection reason:'))
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

check('Profile renewal regression: overview expired and overdue cards offer Renew', () => {
  for (const status of ['OPEN', 'EXPIRED']) {
    const html = render(ProfilePage, '/profile', [{ ...request, status, needed_datetime: '2000-01-01 00:00:00' }], null, undefined, { profile, reports: [] })
    assert.ok(html.includes('aria-label="Request actions"'))
    assert.ok(html.includes('Previous deadline'))
    assert.ok(!html.includes('>Edit</button>'))
  }
})
check('Request modal regression: one bordered cancel and no top close control', () => {
  const modal = readFileSync(frontend + 'src/components/RequestFormModal.jsx', 'utf8')
  const form = readFileSync(frontend + 'src/pages/RequestFormPage.jsx', 'utf8')
  assert.ok(!modal.includes('Close request form') && !modal.includes('modalClose'))
  assert.ok(form.includes('className="btn btn-secondary" disabled={submitting} onClick={onCancel}'))
  assert.ok(form.includes('choose Renew request to set a new deadline'))
})
const DonorPanel = await loadPage('../components/OfferAcceptancePanel')
check('Donor panel uses one surface, tab-specific copy and an upper-left reset toolbar', () => {
  const originalState = React.useState
  try {
    for (const tab of ['offers', 'suggestions']) {
      React.useState = initial => { const state = originalState(initial); return initial === 'offers' ? [tab, state[1]] : state }
      globalThis.requestUiUser = { id: 7, role: 'member' }
      const client = new QueryClient({defaultOptions:{queries:{retry:false}}})
      client.setQueryData(['donation-offers', '45', 7], {offers:[]})
      const html = renderToStaticMarkup(h(QueryClientProvider,{client},h(MemoryRouter,null,h(DonorPanel,{requestId:'45',matches:[]}))))
      assert.ok(html.includes(tab === 'offers' ? 'These donors have offered to help with your request.' : 'These donors match your request but have not offered to help.'))
      assert.equal((html.match(/class="matchesSurface"/g)||[]).length,1)
      assert.ok(html.indexOf('Reset filters') < html.indexOf('id="donor-search"'))
      assert.ok(html.includes('role="tablist"') && html.includes('aria-label="Donor lists"'))
      client.clear()
    }
  } finally { React.useState = originalState }
})
const DeleteAccount = await loadPage('../components/DeleteAccount')
const ChatReportsView = await loadPage('admin/views/ChatReportsView')
check('Expired profile tab renders renewal cards inside the profile content column', () => {
  const originalState = React.useState
  React.useState = initial => { const state = originalState(initial); return initial === 'overview' ? ['expired', state[1]] : state }
  try {
    const html = render(ProfilePage, '/profile', [{...request, status: 'EXPIRED'}], null, undefined, { profile, reports: [] })
    assert.ok(html.indexOf('class="profileContent"') < html.indexOf('id="request-history-heading"'))
    assert.ok(html.includes('aria-label="Request actions"') && html.includes('Previous deadline'))
    assert.ok(!html.includes('type="date"') && !html.includes('type="time"'))
    assert.ok(!html.includes('Request donation follow-up') && html.includes('View matches'))
    assert.ok(!html.includes('Account settings') && !html.includes('Delete account'))
    assert.equal((html.match(/id="request-history-heading"/g) || []).length, 1)
  } finally { React.useState = originalState }
})
check('Expired profile tab has one compact empty state and no renewal form when empty', () => {
  const originalState = React.useState
  React.useState = initial => { const state = originalState(initial); return initial === 'overview' ? ['expired', state[1]] : state }
  try {
    const html = render(ProfilePage, '/profile', [], null, undefined, { profile, reports: [], requests: [] })
    assert.ok(html.includes('No expired requests.')); assert.ok(!html.includes('Renew request'))
  } finally { React.useState = originalState }
})
check('Account deletion is a controlled dialog, preserves identity fields and starts disabled', () => {
  globalThis.requestUiUser = {id:7, role:'member'}
  const show = open => renderToStaticMarkup(h(MemoryRouter, null, h(DeleteAccount, {open, onClose:()=>{}})))
  assert.equal(show(false), '')
  const html = show(true)
  for (const text of ['role="alertdialog"', 'Current password', 'Type DELETE to confirm', 'Keep account']) assert.ok(html.includes(text), text)
  assert.ok(html.includes('disabled=""') && html.includes('>Delete account</button>'))
})
check('Sole administrator deletion dialog offers explanation without deletion inputs', () => {
  globalThis.requestUiUser = {id:3, role:'admin'}
  const html = renderToStaticMarkup(h(MemoryRouter, null, h(DeleteAccount, {open:true, onClose:()=>{}})))
  assert.ok(html.includes('Administrator responsibility must be transferred'))
  assert.ok(html.includes('Understood')); assert.ok(!html.includes('Current password') && !html.includes('Keep editing'))
})
check('Chat Reports loading does not display an empty table or pagination', () => {
  const html = renderToStaticMarkup(h(ChatReportsView))
  assert.ok(html.includes('Loading reports...')); assert.ok(!html.includes('<table') && !html.includes('Report pages'))
})
check('Chat Reports empty view renders one empty state without table or pager', () => {
  const originalState = React.useState
  React.useState = initial => { const state = originalState(initial); return initial === true ? [false,state[1]] : state }
  try {
    const html = renderToStaticMarkup(h(ChatReportsView))
    assert.ok(html.includes('No reports in this view')); assert.ok(!html.includes('<table') && !html.includes('Report pages'))
  } finally { React.useState = originalState }
})

const RequestHistoryActions = await loadPage('../components/RequestHistoryActions')
check('Expanded extension uses separate required date and time fields; closure uses a labelled reason', () => {
  const originalState = React.useState
  try {
    for (const mode of ['renew', 'close']) {
      React.useState = initial => { const state = originalState(initial); return initial === null ? [mode,state[1]] : state }
      const html = renderToStaticMarkup(h(MemoryRouter, null, h(RequestHistoryActions, {request, onSaved:()=>{}})))
      if (mode === 'renew') { assert.ok(html.includes('type="date"') && html.includes('type="time"')); assert.ok(html.includes('New date') && html.includes('New time')); assert.ok(!html.includes('<select')) }
      else { assert.ok(html.includes('Reason for closing')); assert.ok(html.includes('Need met elsewhere')); assert.ok(!html.includes('type="date"')) }
      assert.ok(!html.includes('follow-up'))
    }
  } finally { React.useState = originalState }
})
check('Report review replaces the list, uses names and preserves labelled evidence', () => {
  const originalState = React.useState
  const detail = {report:{id:1, reporter_id:7, reporter_name:'Reporter Name',status:'open',reason:'Reason text',created_at:'2026-10-15 00:00:00'}, evidence:[{message_id:9,sender_id:8,sender_name:'Member Name',body:'Reported body',is_selected:1,message_created_at:'2026-10-15 00:00:00'},{message_id:8,sender_id:7,sender_name:'Reporter Name',body:'Context body',is_selected:0,message_created_at:'2026-10-15 00:00:00'}]}
  React.useState = initial => { const state = originalState(initial); return initial === null ? [detail,state[1]] : state }
  try {
    const html = renderToStaticMarkup(h(ChatReportsView))
    for (const text of ['Back to reports','Review conversation','Reference #1','Member Name','Reporter Name','Reported body','Context body','does not change account access']) assert.ok(html.includes(text),text)
    assert.ok(!html.includes('Account #') && !html.includes('<table') && !html.includes('chat-report-filter'))
  } finally { React.useState = originalState }
})
console.log(`${passed} static checks passed. Browser interactions and backend behavior were not exercised.`)
