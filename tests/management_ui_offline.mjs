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
async function page(entry) {
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
      builder.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api = {get: () => {throw Error("Network forbidden")}, post: () => {throw Error("Network forbidden")}}', loader: 'js' }))
      builder.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default new Proxy({}, {get: (_,key)=>key})', loader: 'js' }))
      builder.onLoad({ filter: /ConfirmationDialog\.jsx$/ }, () => ({ contents: 'export default function Dialog() { return null }', loader: 'jsx' }))
      builder.onLoad({ filter: /.*/, namespace: 'management-source' }, args => ({ contents: readFileSync(args.path, 'utf8'), loader: extname(args.path) === '.jsx' ? 'jsx' : 'js' }))
    }}] })
  const module = { exports: {} }
  const icons = new Proxy({}, { get: () => () => React.createElement('svg') })
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(id => id === '@phosphor-icons/react' ? icons : require(id), module, module.exports)
  return module.exports.default
}
const Users = await page('./src/pages/admin/views/UsersView.jsx')
const Confirmations = await page('./src/pages/officer/views/ConfirmationsView.jsx')
const Verifications = await page('./src/pages/admin/views/VerificationsView.jsx')
const original = React.useState
let mode = 'users'
let index = 0
let inPage = false
let verificationFixture = {user:{id:89,full_name:'Synthetic member',email:'synthetic@example.test',chapter_name:'Reference chapter',date_of_birth:'1990-01-02',blood_type:'A+',verification_status:'pending'},documents:[{id:301,doc_type:'national_id',mime_type:'application/pdf',size_bytes:1024,uploaded_at:'2026-10-11 00:00:00'},{id:302,doc_type:'parental_consent',mime_type:'application/pdf',size_bytes:1024,uploaded_at:'2026-10-11 00:00:00'},{id:303,doc_type:'donor_card',mime_type:'image/jpeg',size_bytes:1024,uploaded_at:'2026-10-11 00:00:00'}],history:[]}
function Fixture({ Component, ...props }) {
  index = 0; inPage = true
  try { return Component(props) } finally { inPage = false }
}
const self = { id: 1, full_name: 'Current admin', email: 'admin@example.test', role: 'admin', chapter_id: 1, account_status: 'active', verification_status: 'verified' }
const other = { ...self, id: 2, full_name: 'Other member', email: 'member@example.test', role: 'member', verification_status: 'pending' }
React.useState = initial => {
  if (!inPage) return original(initial)
  const position = index++
  const state = original(initial)
  if (mode === 'verification' && position === 2) return [verificationFixture,state[1]]
  if (mode === 'users' && position === 0) return [[self, other], state[1]]
  if (mode === 'users' && position === 1) return [[{ id: 1, name: 'First chapter' }], state[1]]
  if (mode === 'confirmations' && position === 0) return [[{ id: 3, match_id: 4, donor_name: 'Self donor', can_confirm: false, can_reject: false, reported_at: '2026-10-10 00:00:00', required_blood_type: 'A+', facility_name: 'Fixture' }], state[1]]
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
  assert.equal((html.match(/disabled=""/g) || []).length, 2)
  const app = readFileSync(`${root}/src/App.jsx`, 'utf8')
  assert(app.includes('path="users" element={<UsersView />}') && app.includes('path="confirmations" element={<ConfirmationsView />}'))
  const analytics = readFileSync(`${root}/src/pages/admin/views/AnalyticsView.jsx`, 'utf8')
  assert(analytics.includes('/api/analytics/export?') && analytics.includes('Export aggregate CSV'))
  mode='verification'
  html=renderToStaticMarkup(React.createElement(MemoryRouter,{initialEntries:['/admin/verifications?user=89']},React.createElement(Fixture,{Component:Verifications})))
  assert(html.includes('Parental consent') && html.includes('Blood donor card'))
  assert(html.includes('/api/admin/documents/302/file') && html.includes('/api/admin/documents/303/file'))
  assert(html.includes('id="staff-accept-donor-card"') && !html.includes('id="staff-accept-donor-card" checked=""'))
  assert(html.includes('not medical confirmation'))
  verificationFixture={...verificationFixture,documents:verificationFixture.documents.filter(document=>document.doc_type!=='donor_card')}
  html=renderToStaticMarkup(React.createElement(MemoryRouter,{initialEntries:['/admin/verifications?user=89']},React.createElement(Fixture,{Component:Verifications})))
  assert(!html.includes('id="staff-accept-donor-card"'))
  const Report = await page('./src/components/AnalyticsPrintReport.jsx')
  const report = renderToStaticMarkup(React.createElement(Report, {scopeLabel:'All chapters',data:{date_from:'2026-10-01',date_to:'2026-10-11',request_volume:{fulfilled_requests:6,resolved_requests:34,fulfillment_rate_percent:17.6},donor_pool:{available:25},users:[{full_name:'PRIVATE NAME',email:'private@example.test',phone:'PRIVATE PHONE'}],documents:[{stored_name:'PRIVATE DOCUMENT'}]}}))
  assert(report.includes('17.6%') && report.includes('current snapshot') && report.includes('All chapters'))
  for(const secret of ['PRIVATE NAME','private@example.test','PRIVATE PHONE','PRIVATE DOCUMENT']) assert(!report.includes(secret))
  console.log('PASS offline management, self-action, confirmation and aggregate-print privacy checks; no API or database.')
} finally { React.useState = original }
