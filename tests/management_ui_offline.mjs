import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const { build } = require('esbuild')
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { MemoryRouter } = require('react-router-dom')
const root = fileURLToPath(new URL('../frontend/', import.meta.url))
async function page(entry, offlineApi = {get: () => {throw Error('Network forbidden')}, post: () => {throw Error('Network forbidden')}}) {
  const result = await build({ absWorkingDir: root, entryPoints: [entry], bundle: true, write: false, platform: 'node', format: 'cjs', packages: 'external', jsx: 'automatic',
    plugins: [{ name: 'offline-management', setup(builder) {
      builder.onResolve({ filter: /.*/ }, args => {
        if (!args.path.startsWith('.') && !isAbsolute(args.path)) return { path: args.path, external: true }
        const path = resolve(args.importer ? dirname(args.importer) : root, args.path)
        const found = [path, `${path}.jsx`, `${path}.js`].find(existsSync)
        if (!found) throw Error(`Missing source: ${path}`)
        return { path: found, namespace: 'management-source' }
      })
      builder.onLoad({ filter: /AuthContext\.jsx$/ }, () => ({ contents: 'export const useAuth = () => ({user: {id: 1, role: "admin"}})', loader: 'js' }))
      builder.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api = offlineApi', loader: 'js' }))
      builder.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default new Proxy({}, {get: (_,key)=>key})', loader: 'js' }))
      builder.onLoad({ filter: /ConfirmationDialog\.jsx$/ }, () => ({ contents: 'export default function Dialog() { return null }', loader: 'jsx' }))
      builder.onLoad({ filter: /.*/, namespace: 'management-source' }, args => ({ contents: readFileSync(args.path, 'utf8'), loader: extname(args.path) === '.jsx' ? 'jsx' : 'js' }))
    }}] })
  const module = { exports: {} }
  const icons = new Proxy({}, { get: () => () => React.createElement('svg') })
  new Function('require', 'module', 'exports', 'offlineApi', result.outputFiles[0].text)(id => id === '@phosphor-icons/react' ? icons : require(id), module, module.exports, offlineApi)
  return module.exports.default
}
const Users = await page('./src/pages/admin/views/UsersView.jsx')
const Confirmations = await page('./src/pages/officer/views/ConfirmationsView.jsx')
const decisionCalls = []
let finishDecision = null
let failDecision = false
const Verifications = await page('./src/pages/admin/views/VerificationsView.jsx', {
  get: async () => ({queue:[],total:0}),
  post: async (path, body) => {
    decisionCalls.push({path,body})
    if (finishDecision) await new Promise(resolve => { finishDecision = resolve })
    if (failDecision) throw Error('Synthetic decision failure')
  },
})
const original = React.useState
let mode = 'users'
let index = 0
let inPage = false
let fixtureTree = null
const verificationStates = new Map()
let verificationFixture = {user:{id:89,full_name:'Synthetic member',email:'synthetic@example.test',chapter_name:'Reference chapter',date_of_birth:'1990-01-02',blood_type:'A+',verification_status:'pending'},documents:[{id:301,doc_type:'national_id',mime_type:'application/pdf',size_bytes:1024,uploaded_at:'2026-10-11 00:00:00'},{id:302,doc_type:'parental_consent',mime_type:'application/pdf',size_bytes:1024,uploaded_at:'2026-10-11 00:00:00'},{id:303,doc_type:'donor_card',mime_type:'image/jpeg',size_bytes:1024,uploaded_at:'2026-10-11 00:00:00'}],history:[]}
function Fixture({ Component, ...props }) {
  index = 0; inPage = true
  try { fixtureTree = Component(props); return fixtureTree } finally { inPage = false }
}
const self = { id: 1, full_name: 'Current admin', email: 'admin@example.test', role: 'admin', chapter_id: 1, account_status: 'active', verification_status: 'verified' }
const other = { ...self, id: 2, full_name: 'Other member', email: 'member@example.test', role: 'member', verification_status: 'pending' }
React.useState = initial => {
  if (!inPage) return original(initial)
  const position = index++
  const state = original(initial)
  if (mode === 'verification') {
    const value = verificationStates.has(position) ? verificationStates.get(position) : position === 2 ? verificationFixture : state[0]
    return [value, next => verificationStates.set(position, typeof next === 'function' ? next(value) : next)]
  }
  if (mode === 'users' && position === 0) return [[self, other], state[1]]
  if (mode === 'users' && position === 1) return [[{ id: 1, name: 'First chapter' }], state[1]]
  if (mode === 'confirmations' && position === 0) return [[{ id: 3, match_id: 4, donor_name: 'Self donor', can_confirm: false, can_reject: false, reported_at: '2026-10-10 00:00:00', required_blood_type: 'A+', facility_name: 'Fixture' }], state[1]]
  if (mode === 'summary' && position === 4) return [{date_from:'2037-01-02',date_to:'2037-01-03',chapter_id:null,total_confirmed_reports:2,daily_confirmations:[{confirmation_date_utc:'2037-01-02',confirmed_reports:2}],users:[{full_name:'PRIVATE CONFIRMATION',email:'private-confirmation@example.test'}]},state[1]]
  if (mode === 'summary' && position === 0) return ['2037-01-02',state[1]]
  if (mode === 'summary' && position === 1) return ['2037-01-03',state[1]]
  if (mode === 'summary' && position === 6) return [false,state[1]]
  if (mode === 'analytics' && position === 0) return [{request_volume:{total_requests:52,total_units_requested:62,fulfillment_rate_percent:15.9,fulfilled_requests:7,resolved_requests:44,cancellation_rate_percent:15.9,cancelled_requests:7,expiration_rate_percent:68.2,expired_requests:30},daily_request_trend:[]},state[1]]
  if (mode === 'analytics' && position === 3) return [false,state[1]]
  return state
}
try {
  index = 0
  let html = renderToStaticMarkup(React.createElement(MemoryRouter, {}, React.createElement(Fixture, { Component: Users })))
  assert(html.includes('User management') && html.includes('Name or email'))
  const selfCard = html.slice(html.indexOf('Current admin'), html.indexOf('Other member'))
  assert.equal((selfCard.match(/disabled=""/g) || []).length, 3)
  assert(html.includes('Change role') && html.includes('Change chapter') && html.includes('Deactivate') && html.includes('Previous') && html.includes('Next'))
  mode = 'confirmations'; index = 0
  html = renderToStaticMarkup(React.createElement(MemoryRouter, {}, React.createElement(Fixture, { Component: Confirmations, admin: true })))
  assert(html.includes('Donation confirmations') && !html.includes('Chapter Queue'))
  assert(html.includes('You cannot review your own report.'))
  assert.equal((html.slice(html.indexOf('Self donor')).match(/disabled=""/g) || []).length, 2)
  const app = readFileSync(`${root}/src/App.jsx`, 'utf8')
  assert(app.includes('path="users" element={<UsersView />}') && app.includes('path="confirmations" element={<ConfirmationsView />}'))
  const analytics = readFileSync(`${root}/src/pages/admin/views/AnalyticsView.jsx`, 'utf8')
  assert(analytics.includes('/api/analytics/export?') && analytics.includes('Export aggregate CSV'))
  mode='verification'
  html=renderToStaticMarkup(React.createElement(MemoryRouter,{initialEntries:['/admin/verifications?user=89']},React.createElement(Fixture,{Component:Verifications})))
  assert(html.includes('Parental consent') && html.includes('Blood donor card'))
  assert(html.includes('Inspect parental consent') && html.includes('Inspect donor card') && !html.includes('target="_blank"'))
  assert(html.includes('id="staff-accept-donor-card"') && !html.includes('id="staff-accept-donor-card" checked=""'))
  assert(html.includes('not medical confirmation'))
  assert(!html.includes('admin-verification-decision') && !html.includes('applicantIcon'))
  assert(!html.includes('Inspect the latest document before making a decision.') && !html.includes('Choose the outcome for this account.'))
  assert(html.includes('centeredSectionHeading') && html.includes('Approve account') && html.includes('Reject verification'))
  const renderVerification = () => renderToStaticMarkup(React.createElement(MemoryRouter,{initialEntries:['/admin/verifications?user=89']},React.createElement(Fixture,{Component:Verifications})))
  const walk = (node, predicate) => {
    if (!React.isValidElement(node)) return null
    if (predicate(node)) return node
    for (const child of React.Children.toArray(node.props.children)) { const found = walk(child,predicate); if(found) return found }
    return null
  }
  const control = id => walk(fixtureTree,node=>node.props.id===id)
  const button = label => walk(fixtureTree,node=>node.type==='button' && React.Children.toArray(node.props.children).some(child=>typeof child==='string' && child.trim()===label))
  const form = () => walk(fixtureTree,node=>node.type==='form')
  const submitEvent = {preventDefault(){}}
  // Exercise real page handlers with mock transport; opening/cancelling rejection never posts.
  control('staff-accept-donor-card').props.onChange({target:{checked:true}})
  renderVerification()
  button('Reject verification').props.onClick()
  html=renderVerification()
  assert(control('admin-verification-reason').props.required)
  assert(!control('staff-accept-donor-card') && button('Submit rejection'))
  assert.equal(decisionCalls.length,0)
  control('admin-verification-reason').props.onChange({target:{value:'   '}})
  renderVerification()
  await form().props.onSubmit(submitEvent)
  assert.equal(decisionCalls.length,0)
  assert(renderVerification().includes('Enter a rejection reason'))
  button('Cancel rejection').props.onClick()
  renderVerification()
  assert.equal(control('staff-accept-donor-card').props.checked,false)
  assert.equal(decisionCalls.length,0)
  button('Reject verification').props.onClick()
  renderVerification()
  control('admin-verification-reason').props.onChange({target:{value:'  Please submit a readable ID.  '}})
  renderVerification()
  await form().props.onSubmit(submitEvent)
  assert.deepEqual(decisionCalls.at(-1),{path:'/api/admin/verifications/89/decision',body:{decision:'rejected',reason:'Please submit a readable ID.',accept_donor_card:false}})
  // Approval retains opt-in evidence and synchronously blocks retries while the request is outstanding.
  verificationStates.clear()
  renderVerification()
  control('staff-accept-donor-card').props.onChange({target:{checked:true}})
  renderVerification()
  finishDecision=true
  const originalSubmit = form().props.onSubmit
  const pending = originalSubmit(submitEvent)
  const afterFirst = decisionCalls.length
  await originalSubmit(submitEvent)
  assert.equal(decisionCalls.length,afterFirst)
  html=renderVerification()
  assert(button('Reject verification').props.disabled && button('Back to queue').props.disabled)
  finishDecision(); finishDecision=null
  await pending
  assert.equal(decisionCalls.at(-1).body.decision,'verified')
  assert.equal(decisionCalls.at(-1).body.accept_donor_card,true)
  assert.equal(decisionCalls.at(-1).body.reason,null)
  verificationStates.clear(); failDecision=true
  renderVerification()
  await form().props.onSubmit(submitEvent)
  html=renderVerification()
  assert(html.includes('Synthetic decision failure') && !button('Approve account').props.disabled)
  failDecision=false; verificationStates.clear()
  const pendingFixture=verificationFixture
  verificationFixture={...verificationFixture,user:{...verificationFixture.user,verification_status:'verified'}}
  renderVerification()
  const beforeStale=decisionCalls.length
  assert(button('Approve account').props.disabled && button('Reject verification').props.disabled)
  await form().props.onSubmit(submitEvent)
  assert.equal(decisionCalls.length,beforeStale)
  verificationFixture=pendingFixture
  verificationFixture={...verificationFixture,documents:verificationFixture.documents.filter(document=>document.doc_type!=='donor_card')}
  html=renderToStaticMarkup(React.createElement(MemoryRouter,{initialEntries:['/admin/verifications?user=89']},React.createElement(Fixture,{Component:Verifications})))
  assert(!html.includes('id="staff-accept-donor-card"'))
  verificationFixture={...verificationFixture,documents:[]}
  renderVerification()
  const beforeMissingId=decisionCalls.length
  assert(button('Approve account').props.disabled && !button('Reject verification').props.disabled)
  await form().props.onSubmit(submitEvent)
  assert.equal(decisionCalls.length,beforeMissingId)
  const Demand = await page('./src/components/charts/BloodGroupDemandChart.jsx')
  const demand = renderToStaticMarkup(React.createElement(Demand,{demandByBt:{'A+':{requests:3,units:7},'O-':{requests:1,units:2}}}))
  assert(demand.includes('7u') && demand.includes('2u') && demand.includes('7 units requested across 3 requests'))
  const emptyDemand = renderToStaticMarkup(React.createElement(Demand,{demandByBt:{}}))
  assert(emptyDemand.includes('0 units requested across 0 requests') && !emptyDemand.includes('NaN'))
  const Summary = await page('./src/components/ConfirmedDonationSummary.jsx')
  // Inject already-loaded aggregate data into the summary; effects/network remain disabled in SSR.
  mode='summary'
  const summaryHtml = renderToStaticMarkup(React.createElement(Fixture,{Component:Summary}))
  assert(summaryHtml.includes('Confirmed donation summary') && summaryHtml.includes('Download CSV'))
  assert(summaryHtml.includes('2 confirmed donation reports') && summaryHtml.includes('2037-01-02'))
  assert(!summaryHtml.includes('PRIVATE CONFIRMATION') && !summaryHtml.includes('private-confirmation@example.test'))
  const Analytics = await page('./src/pages/admin/views/AnalyticsView.jsx')
  mode='analytics'
  const analyticsHtml=renderToStaticMarkup(React.createElement(Fixture,{Component:Analytics}))
  assert(analyticsHtml.indexOf('aria-label="Request metrics"') < analyticsHtml.indexOf('Request volume and fulfillment trend'))
  assert(analyticsHtml.includes('52') && analyticsHtml.includes('62 units requested') && analyticsHtml.includes('15.9%') && analyticsHtml.includes('7 of 44 resolved'))
  assert(analyticsHtml.includes('trendHeading') && analyticsHtml.includes('dateFilters') && analyticsHtml.includes('exportActions'))
  const Report = await page('./src/components/AnalyticsPrintReport.jsx')
  const report = renderToStaticMarkup(React.createElement(Report, {scopeLabel:'All chapters',data:{date_from:'2026-10-01',date_to:'2026-10-11',request_volume:{fulfilled_requests:6,resolved_requests:34,fulfillment_rate_percent:17.6},donor_pool:{available:25},users:[{full_name:'PRIVATE NAME',email:'private@example.test',phone:'PRIVATE PHONE'}],documents:[{stored_name:'PRIVATE DOCUMENT'}]}}))
  assert(report.includes('17.6%') && report.includes('current snapshot') && report.includes('All chapters'))
  for(const secret of ['PRIVATE NAME','private@example.test','PRIVATE PHONE','PRIVATE DOCUMENT']) assert(!report.includes(secret))
  console.log('PASS offline management, self-action, confirmation, verification decision interactions and aggregate-print privacy checks; mock transport only, no network or database.')
} finally { React.useState = original }
