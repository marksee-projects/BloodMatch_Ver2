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
const request = { id: 17, requester_name: 'Rafael Garcia', requester_verification_status: 'verified', requester_chapter_name: 'Mt. Samat Chapter', urgency: 'emergency', required_blood_type: 'AB+', facility_name: 'Mariveles Mental Wellness and General Hospital', location: { municipality_name: 'Mariveles' }, quantity_units: 2, needed_datetime: '2026-10-09 15:00:00', created_at: new Date(Date.now() - 7200000).toISOString().slice(0, 19).replace('T', ' '), can_respond: true }
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
  assert(!collapsed.includes('Mariveles'), 'Hospital and municipality are absent from the collapsed header')
  const summary = cardTree.props.children.props.children[0]
  summary.props.onClick()
  assert(expanded, 'Actual disclosure callback expands the card')
  const details = renderCard(request)
  assert(details.includes('class="hospital"') && details.includes('class="metadata"'), 'Hospital and grouped facts are rendered')
  const facts = details.match(/<dl class="metadata">([\s\S]*?)<\/dl>/)[1]
  assert.deepEqual([...facts.matchAll(/<dt>(.*?)<\/dt>/g)].map(match => match[1]), ['Hospital', 'Municipality', 'Blood needed', 'Needed by'], 'All four facts are inside the same gray details box')
  assert.equal(details.split(request.facility_name).length - 1, 1, 'Long hospital name occurs once')
  assert(details.match(/<button(?![^>]*disabled)[^>]*>Respond<\/button>/), 'Eligible Respond is enabled')
  assert(details.includes('href="/requests/17/matches"'), 'View request retains its existing destination')
  findElement(cardTree, element => element.props?.children === 'Respond').props.onClick()
  assert.equal(responseCalls.length, 0, 'Respond opens confirmation without submitting')
  const confirmationMarkup = renderCard(request)
  assert(confirmationMarkup.includes('role="alertdialog"') && confirmationMarkup.includes('Confirm I can help'), 'Shared accessible confirmation appears')
  assert(confirmationMarkup.includes('final medical eligibility decision'), 'Facility screening notice is shown before responding')
  const dialog = findElement(cardTree, element => element.props?.confirmLabel === 'Confirm I can help')
  dialog.props.onCancel()
  assert.equal(responseCalls.length, 0, 'Cancellation does not submit a response')
  assert(!renderCard(request).includes('role="alertdialog"'), 'Cancellation dismisses confirmation')
  findElement(cardTree, element => element.props?.children === 'Respond').props.onClick()
  renderCard(request)
  const confirmedDialog = findElement(cardTree, element => element.props?.confirmLabel === 'Confirm I can help')
  confirmedDialog.props.onConfirm()
  confirmedDialog.props.onConfirm()
  await new Promise(resolve => setImmediate(resolve))
  assert.deepEqual(responseCalls, [['/api/requests/17/respond']], 'Confirmation submits once through the request-ID API; rapid repeats are blocked')
  assert.equal(confirmingResponse, false, 'Successful mocked response dismisses confirmation')
  confirmingResponse = true
  const staleConfirmation = renderCard({ ...request, can_respond: false, reason_text: 'This request is no longer open.' })
  assert(staleConfirmation.match(/<button[^>]*disabled[^>]*>Confirm I can help<\/button>/), 'Confirmation disables submission when eligibility changes while it is open')
  findElement(cardTree, element => element.props?.confirmLabel === 'Confirm I can help').props.onConfirm()
  assert.equal(responseCalls.length, 1, 'Stale confirmation cannot submit even when its callback is invoked directly')
  confirmingResponse = false
  renderCard(request)
  for (const reason of ['Donation cooldown is active', 'You are unavailable']) {
    const disabled = renderCard({ ...request, can_respond: false, reason_text: reason })
    assert(disabled.match(/<button[^>]*disabled[^>]*>Respond<\/button>/), 'Ineligible and staff Respond remain disabled')
    assert(disabled.includes('class="responseReason"') && disabled.includes(reason.replaceAll("'", '&#x27;')), 'Plain safety reason remains visible')
  }
  const staff = renderCard({ ...request, can_respond: false, reason_code: 'staff_account', reason_text: "Staff accounts can't donate" })
  assert(staff.match(/<button[^>]*disabled[^>]*>Respond<\/button>/), 'Staff Respond stays disabled')
  assert(!staff.includes('Staff accounts') && !staff.includes('class="responseReason"'), 'Staff-only donation message is omitted')
  assert(renderCard({ ...request, urgency: 'routine' }).includes('ROUTINE'), 'Routine wording retained')
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
