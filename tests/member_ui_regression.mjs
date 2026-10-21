// Render current member components with fake query data. No browser/API/database.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
process.env.NODE_ENV = 'production'
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { MemoryRouter } = require('react-router-dom')
const { build } = require('esbuild')
const root = fileURLToPath(new URL('../frontend/', import.meta.url))
const request = { id: 91, match_id: 42, requester_id: 8, requester_name: 'Fixture requester', requester_verification_status: 'verified', requester_chapter_name: 'Fixture chapter',
  required_blood_type: 'A+', urgency: 'routine', facility_name: 'A hospital with a long facility name', location: { municipality_name: 'Balanga' }, quantity_units: 1,
  needed_datetime: '2099-10-22 09:00:00', created_at: '2026-10-21 09:00:00', status: 'OPEN', match_status: 'RESPONDED', can_view_requester_profile: true }
const fixture = globalThis.memberUiFixture = { user: { id: 7, role: 'member', account_status: 'active', verification_status: 'verified' }, responses: [request],
  contexts: [], offer: { conversation_id: 11, request_id: 91 }, reports: [], thread: { id: 11, recipient: { id: 8, full_name: 'Fixture recipient' } } }
const client = { invalidateQueries() {}, clear() {} }
const query = options => {
  const key = options.queryKey[0]
  const data = key === 'my-responses' ? fixture.responses : key === 'donation-offer-status' ? fixture.offer
    : key === 'donation-chat-contexts' ? { contexts: fixture.contexts } : key === 'donation-reports' ? { reports: fixture.reports }
      : key === 'home-municipalities' ? { municipalities: [] } : undefined
  return { data, isPending: false, isLoading: false, refetch: async () => {} }
}
async function load(entry) {
  const result = await build({ absWorkingDir: root, entryPoints: [entry], bundle: true, write: false, format: 'cjs', platform: 'node', packages: 'external', jsx: 'automatic',
    plugins: [{ name: 'member-fixtures', setup(b) {
      b.onResolve({ filter: /.*/ }, args => {
        if (!args.path.startsWith('.') && !isAbsolute(args.path)) return { path: args.path, external: true }
        const base = resolve(args.importer ? dirname(args.importer) : root, args.path)
        const path = [base, `${base}.jsx`, `${base}.js`].find(existsSync)
        if (!path) throw new Error(`Missing source: ${base}`)
        return { path, namespace: 'source' }
      })
      b.onLoad({ filter: /AuthContext\.jsx$/ }, () => ({ contents: 'export const useAuth=()=>({user:globalThis.memberUiFixture.user,refresh:async()=>{}})', loader: 'js' }))
      b.onLoad({ filter: /ChatContext\.jsx$/ }, () => ({ contents: 'export const useChat=()=>({canChat:true,openTabs:[],fetchUnreadCount:async()=>{}})', loader: 'js' }))
      b.onLoad({ filter: /NotificationContext\.jsx$/ }, () => ({ contents: 'export const useNotify=()=>()=>{}; export const useActionNoticeState=()=>[null,()=>{}]', loader: 'js' }))
      b.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api=new Proxy({}, {get:()=>()=>{throw new Error("Network forbidden")}})', loader: 'js' }))
      b.onLoad({ filter: /DemandMapWidget\.jsx$/ }, () => ({ contents: 'export default function(){return null}', loader: 'js' }))
      b.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default new Proxy({}, {get:(_,key)=>key})', loader: 'js' }))
      b.onLoad({ filter: /.*/, namespace: 'source' }, args => ({ contents: readFileSync(args.path, 'utf8').replace('const [thread, setThread] = useState(null)', 'const [thread, setThread] = useState(globalThis.memberUiFixture.thread)'), loader: extname(args.path) === '.jsx' ? 'jsx' : 'js' }))
    } }] })
  const module = { exports: {} }
  const icons = new Proxy({}, { get: () => props => React.createElement('svg', { className: props.className, 'aria-hidden': props['aria-hidden'], 'aria-label': props['aria-label'] }) })
  new Function('require', 'module', 'exports', result.outputFiles[0].text)(id => id === '@phosphor-icons/react' ? icons : id === '@tanstack/react-query' ? {
    useQuery: query, useQueryClient: () => client, useMutation: () => ({ reset() {}, mutate() {} }),
    useInfiniteQuery: () => ({ data: { pages: [{ requests: [request], total_matching: 1 }] }, refetch() {} })
  } : require(id), module, module.exports)
  return module.exports.default
}
const render = (Page, props = {}, path = '/') => renderToStaticMarkup(React.createElement(MemoryRouter, { initialEntries: [path] }, React.createElement(Page, props)))
let checks = 0
function check(value, label) { assert.ok(value, label); checks++; console.log(`PASS ${label}`) }
const Responses = await load('./src/pages/MyResponsesPage.jsx')
let html = render(Responses)
check(html.includes('feedCard') && html.includes('expandedContent'), 'My Responses uses the expanded Home request-card component')
check(html.includes('I donated') && html.includes('Cancel help') && html.includes('Open request chat'), 'Existing response/report/withdraw actions remain visible')
check(!html.includes('Offer to donate'), 'Response cards cannot accidentally offer again through Home actions')
fixture.responses = [{ ...request, match_status: 'CLOSED', status: 'EXPIRED' }]
html = render(Responses)
check(html.includes('I donated') && html.includes('EXPIRED') && !html.includes('Cancel help'), 'Expired historical offer preserves request status and permitted reporting without withdrawal')
fixture.responses = [{ ...request, match_status: 'COMPLETED' }]; fixture.offer.report_id = 5
html = render(Responses)
check(html.includes('View donation report') && html.includes('/donation-reports?report=5') && !html.includes('I donated'), 'Completed donation retains report history without a new report action')
fixture.user.role = 'admin'; fixture.responses = [request]
check(!render(Responses).includes('I donated'), 'Staff cannot use donor reporting controls')
fixture.user.role = 'member'
const Home = await load('./src/pages/HomeFeedPage.jsx')
html = render(Home)
const filterLabels = [...html.matchAll(/<label class="selectFilter[^]*?<\/label>/g)].map(m => m[0])
check(filterLabels.length === 3 && filterLabels.every(label => !label.includes('selectLeadIcon') && (label.match(/<svg/g) || []).length === 1), 'Home filters keep dropdown indicators but remove decorative leading icons')
const Chat = await load('./src/pages/ChatPage.jsx')
html = render(Chat, {}, '/chat?conversation=11')
check(html.includes('Donation reports') && html.includes('href="/donation-reports"'), 'Direct chat exposes donation-report history navigation')
check(!html.includes('Report donation'), 'Direct chat cannot create request-linked reporting controls without context')
fixture.contexts = [{ id: 9, match_id: 42, request_id: 91, donor_id: 7, required_blood_type: 'A+', facility_name: 'Fixture hospital', quantity_units: 1, request_status: 'OPEN', active: true, offer_cycle: 1, current_cycle: 1, report: null }]
html = render(Chat, {}, '/chat?conversation=11&request=91')
check(html.includes('Report donation'), 'Linked donation context restores the request-specific Report donation button')
const Reports = await load('./src/pages/DonationReportsPage.jsx')
html = render(Reports)
check(html.includes('Donation reports') && html.includes('Back') && html.includes('No donation reports found'), 'Reports retain an accessible heading, Back navigation, and empty state')
const css = readFileSync(new URL('../frontend/src/pages/MatchesPage.module.css', import.meta.url), 'utf8')
check(!/data-chat-count='1'/.test(css) && !css.includes('margin-right: 360px'), 'Floating chat no longer applies the regressed left-shifting page offsets')
console.log(`${checks} member UI regression checks passed; mocked data only, no browser/API/database/mail.`)
