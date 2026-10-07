// Offline rendered-markup checks. Run from the repository root: node tests/profile_ui.mjs
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { transformSync } = require('esbuild')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { MemoryRouter, Routes, Route } = require('react-router-dom')
const { QueryClient, QueryClientProvider } = require('@tanstack/react-query')
const sourceRoot = fileURLToPath(new URL('../frontend/src/', import.meta.url))
const originalJsLoader = require.extensions['.js']

function compile(module, filename) {
  const source = readFileSync(filename, 'utf8')
  // Match Vite's implicit JSX runtime for files without a default React import.
  const jsxSource = /import React(?:,| from)/.test(source) ? source : `import React from 'react';\n${source}`
  module._compile(transformSync(jsxSource, { loader: 'jsx', format: 'cjs' }).code, filename)
}
require.extensions['.jsx'] = compile
require.extensions['.css'] = module => { module.exports = {} }
require.extensions['.js'] = (module, filename) => {
  // Phosphor ships CommonJS in a .cjs.js file inside a type:module package.
  if (filename.endsWith('.cjs.js')) module._compile(readFileSync(filename, 'utf8'), filename)
  else if (filename.startsWith(sourceRoot)) compile(module, filename)
  else originalJsLoader(module, filename)
}

const ProfilePage = require('./src/pages/ProfilePage.jsx').default
const RequestFormModal = require('./src/components/RequestFormModal.jsx').default
const profile = {
  id: 42, full_name: 'Regular Ito User', first_name: 'Regular', middle_name: 'Ito', last_name: 'User',
  chapter_name: 'Meridian Heights Chapter', blood_type: 'A-', role: 'member',
  verification_status: 'verified', member_since: '2026-10-01', donor_enrolled: true,
  availability: 'available', availability_window: { blocked: false },
  location: { municipality_name: 'Bagac' }, capabilities: { create_request: true },
  email: 'allowed-contact@example.test', role_label: 'Member', phone: 'private-phone', date_of_birth: 'private-birthdate',
  documents: [{ id: 987, doc_type: 'national_id' }]
}
const request = {
  id: 101, required_blood_type: 'B-', urgency: 'emergency', facility_name: 'Bataan Doctors Hospital',
  location: { municipality_name: 'Bagac' }, quantity_units: 1, status: 'OPEN',
  needed_datetime: '2026-10-15 15:00:00', created_at: new Date(Date.now() - 172800000).toISOString().slice(0, 19).replace('T', ' '),
  match_count: 4, response_count: 0
}

let selectedSection = 'overview'
let renderedActions = []
const originalUseState = React.useState
const originalCreateElement = React.createElement
// Select the section through the actual navigation callback, then render its state.
React.useState = initial => {
  const state = originalUseState(initial === 'overview' ? selectedSection : initial)
  return initial === 'overview' ? [state[0], value => { selectedSection = value }] : state
}
React.createElement = (type, props, ...children) => {
  if (props?.onClick) renderedActions.push({ type, props, children })
  return originalCreateElement(type, props, ...children)
}
function render(data, other = false) {
  renderedActions = []
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(other ? ['member-profile', '42'] : ['profile'], data)
  const element = React.createElement(ProfilePage)
  const routes = React.createElement(Routes, null,
    React.createElement(Route, { path: '/profile', element }),
    React.createElement(Route, { path: '/profile/:id', element }))
  const tree = React.createElement(QueryClientProvider, { client },
    React.createElement(MemoryRouter, { initialEntries: [other ? '/profile/42' : '/profile'] }, routes))
  const markup = renderToStaticMarkup(tree)
  client.clear()
  return markup
}

const originalError = console.error
console.error = (message, ...args) => {
  if (!String(message).includes('useLayoutEffect does nothing on the server')) originalError(message, ...args)
}
try {
  // Assert actual rendered navigation in the visible header for every owner role.
  // The previous member-only fixture missed the role gate that omitted two tabs.
  for (const role of ['member', 'officer', 'admin']) {
    selectedSection = 'overview'
    const ownerData = { profile: { ...profile, role }, reports: [{ id: 22, required_blood_type: 'A-', facility_name: 'Owner Donation Clinic', reported_at: '2026-10-01 12:00:00', status: 'CONFIRMED' }], requests: [request] }
    const markup = render(ownerData)
    const header = markup.match(/<header\b[^>]*>([\s\S]*?)<\/header>/)?.[1]
    const nav = header?.match(/<nav\b[^>]*aria-label="Profile sections"[^>]*>([\s\S]*?)<\/nav>/)?.[1]
    assert(nav, `${role}: navigation is inside the profile header`)
    assert.deepEqual([...nav.matchAll(/<button\b[^>]*>([^<]*)<\/button>/g)].map(match => match[1]), ['Overview', 'Verification', 'History'], `${role}: exactly three rendered navigation buttons`)
    assert(header.includes('Manage requests'), `${role}: Manage requests is in the header`)
    if (role === 'admin') {
      assert(header.includes('<p>Admin</p>'), 'Admin owner is identified as Admin in the profile header')
      assert(!header.includes('Meridian Heights Chapter member'), 'Admin header does not describe the account as a chapter member')
    }
    for (const [label, expected] of [['Verification', 'View uploaded ID'], ['History', 'Owner Donation Clinic'], ['Overview', 'Bataan Doctors Hospital']]) {
      const action = renderedActions.find(action => action.type === 'button' && action.children.includes(label))
      assert(action && !action.props.disabled, `${role}: ${label} is reachable`)
      action.props.onClick()
      const panel = render(ownerData)
      assert(panel.includes(expected), `${role}: ${label} renders the existing panel/data`)
      const selectedNav = panel.match(/<nav\b[^>]*aria-label="Profile sections"[^>]*>([\s\S]*?)<\/nav>/)[1]
      assert(selectedNav.includes(`aria-current="page">${label}</button>`), `${role}: selected navigation retains its active state`)
    }
  }
  selectedSection = 'overview'
  const owner = render({ profile, reports: [], requests: [request] })
  for (const text of ['About', 'Overview', 'Verification', 'History', 'October 2026', 'Set unavailable', 'Edit profile', 'Blood requests', 'Bataan Doctors Hospital', '4 potential donors', '0 responses', 'Posted 2 days ago', 'View matches', 'Cancel request']) assert(owner.includes(text), text)
  assert(!owner.includes('<form'), 'Overview must not contain editable forms')
  assert(!owner.includes('Request history'), 'No request-history navigation')
  assert(!owner.includes('Collapse profile navigation'), 'No sidebar navigation')
  assert(owner.includes('Manage requests'), 'Request management shortcut retained')
  const createActions = renderedActions.filter(action => action.type.name === 'Button' && action.children.includes('Create request') || action.type.name === 'Button' && action.children.includes(' Create request'))
  assert.equal(createActions.length, 1, 'Only the Profile header Create request button is rendered')
  assert.equal(typeof createActions[0].props.onClick, 'function', 'Header Create request remains actionable')
  assert.equal(createActions[0].props.variant || 'primary', 'primary')
  const ownerHeader = owner.match(/<header\b[^>]*>([\s\S]*?)<\/header>/)[1]
  assert(ownerHeader.includes('Create request') && ownerHeader.includes('Manage requests'), 'Create request and Manage requests remain in the header')
  const donationAction = renderedActions.find(action => action.type === 'button' && action.children.includes('History'))
  assert(donationAction, 'History is reachable from header navigation')
  donationAction.props.onClick()
  const pastRequests = ['FULFILLED', 'CANCELLED', 'EXPIRED'].map((status, index) => ({ ...request, id: 200 + index, facility_name: `Past ${status} Clinic`, status }))
  const donationData = { profile, requests: [request, ...pastRequests], reports: [
    { id: 1, required_blood_type: 'A-', facility_name: 'Donation Clinic One', reported_at: '2026-10-01 12:00:00', status: 'CONFIRMED' },
    { id: 2, required_blood_type: 'B+', facility_name: 'Donation Clinic Two', reported_at: '2026-10-02 12:00:00', status: 'REJECTED' },
    { id: 3, required_blood_type: 'O+', facility_name: 'Donation Clinic Three', reported_at: '2026-10-03 12:00:00', status: 'PENDING' }
  ] }
  const donations = render(donationData)
  assert(!donations.includes('>History</h2>'), 'History does not repeat the tab name as a content heading')
  for (const text of ['donation-history-heading', 'Donation Clinic One', 'Donation Clinic Two', 'Donation Clinic Three', 'Confirmed', 'Rejected', 'Pending']) assert(donations.includes(text), `Donation history: ${text}`)
  assert(!donations.includes('Bataan Doctors Hospital'), 'Request posts stay on Overview')
  const donationSection = donations.match(/<section\b[^>]*aria-labelledby="donation-history-heading"[^>]*>([\s\S]*?)<\/section>/)?.[1]
  const requestSection = donations.match(/<section\b[^>]*aria-labelledby="request-history-heading"[^>]*>([\s\S]*?)<\/section>/)?.[1]
  assert(donationSection && requestSection, 'History has separate labelled donation and request sections')
  assert(donationSection.includes('Donation Clinic One') && !donationSection.includes('Past FULFILLED Clinic'), 'Donation records remain in their own section')
  for (const status of ['FULFILLED', 'CANCELLED', 'EXPIRED']) assert(requestSection.includes(`Past ${status} Clinic`), `${status} request is reachable in History`)
  assert(!requestSection.includes('Donation Clinic One') && !requestSection.includes('Bataan Doctors Hospital'), 'Request history excludes donations and active requests')
  assert(requestSection.includes('View matches'), 'Historical request detail action retained')
  const emptyHistory = render({ profile, requests: [], reports: [] })
  const emptyDonation = emptyHistory.match(/<section\b[^>]*aria-label="Donation history"[^>]*>([\s\S]*?)<\/section>/)?.[1]
  assert(emptyDonation?.includes('No donation history yet.'), 'Compact donation empty state retained')
  assert(!emptyDonation.includes('ui-card') && !emptyDonation.includes('<h3'), 'Empty donation state has no extra heading or card wrapper')
  assert(render({ profile, requests: [], reports: [] }).includes('No past blood requests yet.'), 'Request-history empty state is accurate')
  renderedActions.find(action => action.type === 'button' && action.children.includes('Verification')).props.onClick()
  assert(render({ profile, reports: [], requests: [] }).includes('View uploaded ID'), 'Existing verification document action retained')
  for (const documents of [[], profile.documents]) {
    const verified = render({ profile: { ...profile, documents }, reports: [], requests: [] })
    assert(!verified.includes('id="profile-national-id"') && !verified.includes('Choose a file'), 'Verified accounts cannot select or reupload a National ID, even without a document record')
    assert(!verified.includes('Your account status and submitted identification.'), 'Highlighted introductory sentence removed')
    assert(verified.includes('National ID uploads are locked after verification.'), 'Verified upload lock is explained')
    assert(verified.includes('Review the privacy notice'), 'Privacy notice remains available when verified')
    assert(!verified.includes('ui-badge'), 'Verified green badge removed from the Verification card')
    if (documents.length) assert(verified.includes('View uploaded ID'), 'Existing verified document remains viewable')
  }
  for (const mime_type of ['image/jpeg', 'image/png', 'image/webp']) {
    const preview = render({ profile: { ...profile, documents: [{ id: 987, doc_type: 'national_id', mime_type }] }, reports: [], requests: [] })
    assert(preview.includes('src="/api/profile/documents/987/file"') && preview.includes('alt="Your uploaded National ID"'), 'Uploaded images use the existing owner-protected endpoint')
    assert(!preview.includes('Choose a file'), 'An image preview does not unlock verified uploads')
  }
  const pdf = render({ profile: { ...profile, documents: [{ id: 987, doc_type: 'national_id', mime_type: 'application/pdf' }] }, reports: [], requests: [] })
  assert(!pdf.includes('alt="Your uploaded National ID"') && pdf.includes('View uploaded ID'), 'PDF documents retain their view action without a broken image preview')
  const upload = render({ profile: { ...profile, verification_status: 'pending', documents: [] }, reports: [], requests: [] })
  assert(!upload.includes('>Verification</h2>'), 'Verification does not repeat its tab heading')
  assert(upload.includes('id="verification-section"'), 'Verification content is in its existing card')
  assert(upload.match(/<input[^>]*id="profile-national-id"[^>]*type="file"[^>]*accept="image\/jpeg,image\/png,image\/webp,application\/pdf"/), 'Verification file upload and accepted types retained')
  assert(upload.includes('up to 5 MB') && upload.includes('Choose a file'), 'Verification upload guidance remains reachable')
  assert(upload.match(/<button[^>]*type="button"[^>]*>Review the privacy notice<\/button>/), 'Privacy notice remains a separate keyboard-accessible text control')
  assert(renderedActions.some(action => action.type === 'button' && action.children.includes('Review the privacy notice')), 'Privacy notice has a working action')
  assert(render({ profile: { ...profile, verification_status: 'rejected' }, reports: [], requests: [] }).includes('Choose a replacement ID'), 'Rejected-verification replacement workflow retained')
  renderedActions.find(action => action.type === 'button' && action.children.includes('Overview')).props.onClick()
  const activeOverview = render(donationData)
  assert(activeOverview.includes('Bataan Doctors Hospital'), 'OPEN request remains on Overview')
  for (const status of ['FULFILLED', 'CANCELLED', 'EXPIRED']) assert(!activeOverview.includes(`Past ${status} Clinic`), 'Past requests are not duplicated on Overview')

  const modalClient = new QueryClient()
  const modal = renderToStaticMarkup(React.createElement(QueryClientProvider, { client: modalClient }, React.createElement(MemoryRouter, null,
    React.createElement(RequestFormModal, { open: true, onClose: () => {}, onSuccess: () => {} }))))
  assert(modal.includes('role="dialog"') && modal.includes('aria-label="Create blood request"'), 'Shared create modal is rendered')
  assert.equal((modal.match(/<form\b/g) || []).length, 1, 'One existing request form in the shared modal')
  modalClient.clear()

  const closed = render({ profile, reports: [], requests: [{ ...request, status: 'CANCELLED' }] })
  assert(!closed.includes('Cancel request'), 'Closed requests have no cancel action')
  assert(!closed.includes('>Edit</button>'), 'Closed requests have no edit action')
  const locked = render({ profile: { ...profile, availability_window: { blocked: true, which: 'cooldown', ends_at_utc: '2026-10-15 00:00:00' } }, reports: [], requests: [] })
  assert(locked.match(/<button[^>]*disabled[^>]*>Availability locked<\/button>/), 'Donation window remains locked')
  const restricted = render({ profile: { ...profile, capabilities: { create_request: false } }, reports: [], requests: [] })
  assert(restricted.match(/<button[^>]*disabled[^>]*>Create request<\/button>/), 'Create honors backend capability')

  // Deliberately inject owner fields and activity: a public render must ignore them.
  const publicMarkup = render({ profile, reports: [{ facility_name: 'PRIVATE DONATION' }], requests: [request] }, true)
  for (const text of ['Regular Ito User', 'Meridian Heights Chapter member', 'About', 'A-', 'October 2026', 'Verified', 'mailto:allowed-contact@example.test', '<dt>Role</dt>']) assert(publicMarkup.includes(text), text)
  for (const text of ['Create request', 'Set unavailable', 'Edit profile', 'Cancel request', 'View matches', 'Bagac', 'Donor status', 'private-phone', 'private-birthdate', 'PRIVATE DONATION', 'Bataan Doctors Hospital', 'profile-national-id', 'profile-picture', 'History']) assert(!publicMarkup.includes(text), `Public privacy: ${text}`)
  console.log('PASS: profile navigation, single header Create request, shared modal, compact history empty state, no repeated tab headings, history data, verification upload, safety states, and public privacy.')
} finally {
  console.error = originalError
  React.useState = originalUseState
  React.createElement = originalCreateElement
}
