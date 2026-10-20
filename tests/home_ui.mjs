// Offline rendered-component/callback checks; response API is mocked, no database/network mutation.
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { transformSync } = require('esbuild')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { MemoryRouter } = require('react-router-dom')
const { QueryClient, QueryClientProvider } = require('@tanstack/react-query')
const sourceRoot = fileURLToPath(new URL('../frontend/src/', import.meta.url))
const originalJsLoader = require.extensions['.js']
function compile(module, filename) {
  const source = readFileSync(filename, 'utf8')
  const jsxSource = /import React(?:,| from)/.test(source) ? source : `import React from 'react';\n${source}`
  module._compile(transformSync(jsxSource, { loader: 'jsx', format: 'cjs' }).code, filename)
}
require.extensions['.jsx'] = compile
require.extensions['.css'] = (module, filename) => {
  module.exports = Object.fromEntries([...readFileSync(filename, 'utf8').matchAll(/\.([a-zA-Z][\w-]*)/g)].map(match => [match[1], match[1]]))
}
require.extensions['.js'] = (module, filename) => {
  if (filename.endsWith('.cjs.js')) module._compile(readFileSync(filename, 'utf8'), filename)
  else if (filename.startsWith(sourceRoot)) compile(module, filename)
  else originalJsLoader(module, filename)
}
const { FeedCard } = require('./src/components/FeedCard.jsx')
const Home = require('./src/pages/HomeFeedPage.jsx').default
const DemandMap = require('./src/components/DemandMapWidget.jsx').default
const { api } = require('./src/services/apiClient.js')
const originalPost = api.post
const responseCalls = []
api.post = async (...args) => { responseCalls.push(args); return { status: 'RESPONDED' } }
const request = { id: 17, requester_id: 5, can_view_requester_profile: true, requester_name: 'Rafael Garcia', requester_verification_status: 'verified', requester_chapter_name: 'Mt. Samat Chapter', urgency: 'emergency', required_blood_type: 'AB+', facility_name: 'Mariveles Mental Wellness and General Hospital', location: { municipality_name: 'Mariveles' }, quantity_units: 2, needed_datetime: '2026-10-09 15:00:00', created_at: new Date(Date.now() - 7200000).toISOString().slice(0, 19).replace('T', ' '), can_respond: true }
let expanded = false
let confirmingResponse = false
let inCard = false
let cardStateIndex = 0
let cardTree
let inDemand = false
let demandStateIndex = 0
let demandTree
const demandStates = []
const originalState = React.useState
const originalError = console.error
React.useState = initial => {
  if (inDemand) {
    const index = demandStateIndex++
    if (!(index in demandStates)) demandStates[index] = initial
    const state = originalState(demandStates[index])
    return [state[0], value => { demandStates[index] = typeof value === 'function' ? value(demandStates[index]) : value }]
  }
  const state = originalState(inCard && cardStateIndex === 0 ? expanded : inCard && cardStateIndex === 1 ? confirmingResponse : initial)
  if (inCard) {
    const index = cardStateIndex++
    if (index === 0) return [state[0], value => { expanded = typeof value === 'function' ? value(expanded) : value }]
    if (index === 1) return [state[0], value => { confirmingResponse = typeof value === 'function' ? value(confirmingResponse) : value }]
  }
  return state
}
function FixtureDemandMap() {
  inDemand = true
  demandStateIndex = 0
  try { demandTree = DemandMap({ mode: 'mini' }); return demandTree }
  finally { inDemand = false }
}
function findElement(tree, predicate) {
  if (Array.isArray(tree)) return tree.map(child => findElement(child, predicate)).find(Boolean)
  if (!tree || typeof tree !== 'object') return undefined
  return predicate(tree) ? tree : findElement(tree.props?.children, predicate)
}
function FixtureCard(props) {
  inCard = true
  cardStateIndex = 0
  try { cardTree = FeedCard(props); return cardTree }
  finally { inCard = false }
}
function wrap(element, url = '/', setup = () => {}) {
  const client = new QueryClient()
  setup(client)
  const markup = renderToStaticMarkup(React.createElement(QueryClientProvider, { client }, React.createElement(MemoryRouter, { initialEntries: [url] }, element)))
  client.clear()
  return markup
}
const renderCard = data => wrap(React.createElement(FixtureCard, { request: data }))
console.error = (message, ...args) => {
  if (!String(message).includes('useLayoutEffect does nothing on the server')) originalError(message, ...args)
}
try {
  const collapsed = renderCard(request)
  for (const text of ['Rafael Garcia', 'Verified member', 'Mt. Samat Chapter member', 'Posted 2 hours ago', 'EMERGENCY', 'AB+']) assert(collapsed.includes(text), text)
  assert(collapsed.includes(request.facility_name) && collapsed.includes('Blood needed') && collapsed.includes('Needed by'), 'Essential donation facts remain visible when collapsed')
  assert(!collapsed.includes('<dt>Municipality</dt>') && !collapsed.includes('Request message'), 'Secondary location remains in details and unwanted messaging is absent')
  const summary = findElement(cardTree, element => element.type === 'button' && element.props['aria-controls'])
  summary.props.onClick()
  assert(expanded, 'Actual disclosure callback expands the card')
  const details = renderCard(request)
  assert(details.includes('class="hospital"') && details.includes('class="metadata"'), 'Hospital and grouped facts are rendered')
  const facts = [...details.matchAll(/<dl class="metadata[^\"]*">([\s\S]*?)<\/dl>/g)].map(match => match[1]).join('')
  assert.deepEqual([...facts.matchAll(/<dt>(.*?)<\/dt>/g)].map(match => match[1]), ['Hospital', 'Blood needed', 'Needed by', 'Municipality'], 'Essential facts and secondary details are grouped without duplication')
  assert.equal(details.split(request.facility_name).length - 1, 1, 'Long hospital name occurs once')
  assert(details.match(/<button(?![^>]*disabled)[^>]*>Offer to donate<\/button>/), 'Eligible Offer to donate is enabled')
  assert(!details.includes('>View request</a>'), 'Expanded Home card omits the redundant View request action')
  assert.equal((details.match(/href="\/profile\/5"/g) || []).length, 2, 'Name and avatar link to the authorized requester profile')
  assert(!renderCard({ ...request, can_view_requester_profile: false }).includes('href="/profile/5"'), 'Denied profile access renders no profile link')
  assert(renderCard({ ...request, requester_profile_picture_url: '/api/profile/picture?user_id=5&v=123' }).includes('src="/api/profile/picture?user_id=5&amp;v=123"'), 'Card reuses the existing account picture URL unchanged')
  assert(!/<button[^>]*>[\s\S]*?<a[\s\S]*?<\/button>/.test(details.match(/<header[\s\S]*?<\/header>/)[0]), 'Profile links are separate from the disclosure button')
  findElement(cardTree, element => element.props?.children === 'Offer to donate').props.onClick()
  assert.equal(responseCalls.length, 0, 'Offer to donate opens confirmation without submitting')
  const confirmationMarkup = renderCard(request)
  assert(confirmationMarkup.includes('role="alertdialog"') && confirmationMarkup.includes('Offer to donate'), 'Shared accessible confirmation appears')
  assert(confirmationMarkup.includes('final medical eligibility decision'), 'Facility screening notice is shown before responding')
  const dialog = findElement(cardTree, element => element.props?.confirmLabel === 'Offer to donate')
  dialog.props.onCancel()
  assert.equal(responseCalls.length, 0, 'Cancellation does not submit a response')
  assert(!renderCard(request).includes('role="alertdialog"'), 'Cancellation dismisses confirmation')
  findElement(cardTree, element => element.props?.children === 'Offer to donate').props.onClick()
  renderCard(request)
  const confirmedDialog = findElement(cardTree, element => element.props?.confirmLabel === 'Offer to donate')
  confirmedDialog.props.onConfirm()
  confirmedDialog.props.onConfirm()
  await new Promise(resolve => setImmediate(resolve))
  assert.deepEqual(responseCalls, [['/api/requests/17/respond']], 'Confirmation submits once through the request-ID API; rapid repeats are blocked')
  assert.equal(confirmingResponse, false, 'Successful mocked response dismisses confirmation')
  confirmingResponse = true
  const staleConfirmation = renderCard({ ...request, can_respond: false, reason_text: 'This request is no longer open.' })
  assert(staleConfirmation.match(/<button[^>]*disabled[^>]*>Offer to donate<\/button>/), 'Confirmation disables submission when eligibility changes while it is open')
  findElement(cardTree, element => element.props?.confirmLabel === 'Offer to donate').props.onConfirm()
  assert.equal(responseCalls.length, 1, 'Stale confirmation cannot submit even when its callback is invoked directly')
  confirmingResponse = false
  renderCard(request)
  for (const reason of ['Donation cooldown is active', 'You are unavailable']) {
    const disabled = renderCard({ ...request, can_respond: false, reason_text: reason })
    assert(disabled.match(/<button[^>]*disabled[^>]*>Offer to donate<\/button>/), 'Ineligible and staff Offer to donate remain disabled')
    assert(disabled.includes('class="responseReason"') && disabled.includes(reason.replaceAll("'", '&#x27;')), 'Plain safety reason remains visible')
  }
  const staff = renderCard({ ...request, can_respond: false, reason_code: 'staff_account', reason_text: "Staff accounts can't donate" })
  assert(staff.match(/<button[^>]*disabled[^>]*>Offer to donate<\/button>/), 'Staff Offer to donate stays disabled')
  assert(!staff.includes('Staff accounts') && !staff.includes('class="responseReason"'), 'Staff-only donation message is omitted')
  for (const [code, reason] of [['not_enrolled','Enroll as a donor before responding.'],['membership_unverified','Complete account verification before donating.'],['pending_report_hold','A claimed donation remains unresolved.'],['cooldown','The post-donation cooldown period is still active.']]) {
    const denied = renderCard({ ...request, can_respond: false, reason_code: code, reason_text: reason })
    assert(denied.match(/<button[^>]*disabled[^>]*>Offer to donate<\/button>/), `${code} keeps the main action disabled`)
    assert(denied.includes(reason), `${code} keeps the server eligibility reason visible`)
    findElement(cardTree, element => element.props?.children === 'Offer to donate').props.onClick()
    assert.equal(confirmingResponse, false, 'Direct invocation cannot open an ineligible offer confirmation')
    assert.equal(responseCalls.length, 1, 'Blocked offer creates no request, even if its callback is invoked')
    if (code === 'not_enrolled') assert(denied.includes('Enroll as donor</span>'), 'Enrollment has a separate relevant CTA')
    if (code === 'pending_report_hold') assert(denied.includes('href="/donation-reports"'), 'Held donor can resolve the report')
  }
  assert(renderCard({ ...request, urgency: 'routine' }).includes('ROUTINE'), 'Routine wording retained')
  expanded = false
  const collapsedResponse = renderCard({ ...request, responded: true, can_respond: false })
  assert(collapsedResponse.includes('class="responseStatus"') && collapsedResponse.includes('Responded'), 'Responded status remains visible on a collapsed card')
  assert(!collapsedResponse.includes('class="expandedContent"'), 'Top status does not require expanded details')
  expanded = true
  const expandedResponse = renderCard({ ...request, responded: true, can_respond: false })
  assert(!expandedResponse.match(/<button[^>]*>Offer to donate(?:ed)?<\/button>/), 'Already responded action is replaced by the top status')
  expanded = false
  function home(url, requests) {
    const params = new URLSearchParams(url.split('?')[1] || '')
    params.delete('page')
    return wrap(React.createElement(Home), url, client => {
      client.setQueryData(['home-feed', undefined, undefined, undefined, undefined, undefined, undefined, params.toString()], { pages: [{ requests, page: 1, has_more: true, total_matching: requests.length, total_unfiltered: requests.length }], pageParams: [1] })
      client.setQueryData(['home-municipalities'], { municipalities: [{ psgc_code: '030807000', name: 'Mariveles' }] })
    })
  }
  const unfiltered = home('/', [request])
  for (const label of ['All municipalities', 'All blood types', 'All urgencies', 'Reset filters', 'Load more']) assert(unfiltered.includes(label), label)
  const filtered = home('/?municipality_code=030807000&blood=AB%2B&urgency=emergency', [request])
  for (const label of ['Mariveles', 'AB+', 'Emergency', 'Reset filters']) assert(filtered.includes(label), label)
  for (const markup of [unfiltered, filtered]) {
    assert(!markup.includes('type="checkbox"') && !markup.includes('class="filterChip"'), 'No checkbox dropdowns or selected-filter chips')
    assert.equal((markup.match(/<select\b/g) || []).length, 3, 'All three Home filters are native dropdowns')
    assert.equal((markup.match(/class="selectCaret"/g) || []).length, 3, 'All three filters use the same arrow')
  }
  assert(filtered.includes('value="AB+" selected=""') && filtered.includes('value="emergency" selected=""'), 'Selected filter values remain visible in their dropdowns')
  const sharedMultiFilter = home('/?blood=A%2B%2CB%2B&urgency=urgent%2Cemergency', [request])
  assert(sharedMultiFilter.includes('value="A+,B+" selected=""') && sharedMultiFilter.includes('value="urgent,emergency" selected=""'), 'Existing shared multi-value URLs remain represented until a new single value is selected')
  for (const markup of [unfiltered, filtered]) {
    const header = markup.match(/<div class="filterHeader">([\s\S]*?)<\/div>/)?.[1]
    assert(header?.includes('Reset filters') && header.includes('Compatible requests'), 'Reset and centered heading share the top card header')
    assert(header.indexOf('Reset filters') < header.indexOf('Compatible requests'), 'Reset control precedes the centered heading')
    assert.equal((markup.match(/>Reset filters<\/button>/g) || []).length, 1, 'One reset control for active/inactive filters')
  }
  const hidden = home('/', [])
  assert(!hidden.includes('Rafael Garcia'), 'Requests omitted by the backend are not rendered')
  // Seed only local state; no effect/API request is executed by server rendering.
  demandStates[0] = [
    { chapter_id: 1, chapter_name: 'Mt. Samat Chapter', open_requests_count: 3, urgency_counts: { emergency: 1, urgent: 2, routine: 0 } },
    { chapter_id: 2, chapter_name: 'Mt. Tarak Chapter', open_requests_count: 4, urgency_counts: { emergency: 2, urgent: 0, routine: 2 } }
  ]
  demandStates[1] = false
  let mapMarkup = wrap(React.createElement(FixtureDemandMap))
  const filterMenu = mapMarkup.match(/<details\b[^>]*>([\s\S]*?)<\/details>/)?.[1]
  assert(filterMenu?.includes('<span>Filter</span>') && filterMenu.includes('home-demand-chapter') && filterMenu.includes('home-demand-urgency'), 'Filter dropdown contains both inner dropdowns')
  assert(filterMenu.includes('<span>Chapter</span>') && filterMenu.includes('<span>Urgency level</span>'), 'Inner dropdowns have clear labels')
  assert(filterMenu.indexOf('home-demand-chapter') < filterMenu.indexOf('home-demand-urgency'), 'Chapter appears before Urgency level')
  assert.equal((mapMarkup.match(/>Reset filters<\/button>/g) || []).length, 1, 'Chapter filters have a single reset control')
  findElement(demandTree, element => element.type === 'select' && element.props.id === 'home-demand-chapter').props.onChange({ target: { value: '1' } })
  findElement(demandTree, element => element.type === 'select' && element.props.id === 'home-demand-urgency').props.onChange({ target: { value: 'emergency' } })
  mapMarkup = wrap(React.createElement(FixtureDemandMap))
  assert(mapMarkup.includes('Mt. Samat Chapter: 1 matching active request') && !mapMarkup.includes('Mt. Tarak Chapter: 4 matching'), 'Chapter/urgency selection preserves displayed count behavior')
  findElement(demandTree, element => element.type === 'button' && element.props.children === 'Reset filters').props.onClick()
  mapMarkup = wrap(React.createElement(FixtureDemandMap))
  assert(mapMarkup.includes('Mt. Samat Chapter: 3 matching active requests') && mapMarkup.includes('Mt. Tarak Chapter: 4 matching active requests'), 'Map reset restores all chapters and urgencies')
  console.log('PASS: grouped card, response confirmation/cancellation/repeat/stale safety, native Home filters, pagination, Chapter/urgency reset. Response API mocked; no real API mutations.')
} finally {
  React.useState = originalState
  api.post = originalPost
  console.error = originalError
}
