// Component handlers with a small hook harness and fake API. No browser or network.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { existsSync, readFileSync } from 'node:fs'
import { dirname, extname, isAbsolute, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const React = require('react')
const { build } = require('esbuild')
const complete = { id: 1, name: 'Complete hospital', address_complete: true, location: { location_id: 20, municipality_code: 'balanga', municipality_name: 'City of Balanga', barangay_code: 'saved', barangay_name: 'Saved barangay' } }
const incomplete = { id: 2, name: 'Municipal hospital', address_complete: false, location: { location_id: 10, municipality_code: 'mariveles', municipality_name: 'Mariveles', barangay_code: null } }
const writes = []
globalThis.facilityApi = {
  get: async path => path === '/api/hospitals' ? { hospitals: [complete, incomplete] } : { request: {
    required_blood_type: 'O+', quantity_units: 1, facility_name: complete.name, hospital_id: 1,
    urgency: 'routine', needed_datetime: '2099-12-31 12:00:00', location: { location_id: 999, municipality_code: 'old', barangay_code: 'old' }
  } },
  put: async (path, payload) => { writes.push({ path, payload }); return {} }
}
const frontend = fileURLToPath(new URL('../frontend/', import.meta.url))
const result = await build({
  absWorkingDir: frontend,
  entryPoints: ['./src/pages/RequestFormPage.jsx'], bundle: true, write: false,
  format: 'cjs', platform: 'node', packages: 'external', jsx: 'automatic',
  plugins: [{ name: 'isolated-form-context', setup(builder) {
    builder.onResolve({ filter: /.*/ }, args => {
      if (!args.path.startsWith('.') && !isAbsolute(args.path)) return { path: args.path, external: true }
      const path = resolve(args.importer ? dirname(args.importer) : frontend, args.path)
      const found = [path, `${path}.jsx`, `${path}.js`].find(existsSync)
      if (!found) throw new Error(`Missing source: ${path}`)
      return { path: found, namespace: 'form-fixture' }
    })
    builder.onLoad({ filter: /AuthContext\.jsx$/ }, () => ({ contents: 'export const useAuth = () => ({user:{id:7},refresh:()=>{}})', loader: 'js' }))
    builder.onLoad({ filter: /NotificationContext\.jsx$/ }, () => ({ contents: 'export const useNotify=()=>()=>{}; export const useActionNoticeState=()=>[null,()=>{}]', loader: 'js' }))
    builder.onLoad({ filter: /apiClient\.js$/ }, () => ({ contents: 'export const api=globalThis.facilityApi', loader: 'js' }))
    builder.onLoad({ filter: /\.css$/ }, () => ({ contents: 'export default {}', loader: 'js' }))
    builder.onLoad({ filter: /.*/, namespace: 'form-fixture' }, args => ({ contents: readFileSync(args.path, 'utf8'), loader: extname(args.path) === '.jsx' ? 'jsx' : 'js' }))
  } }]
})
const slots = []; let cursor = 0; const effects = []
const hooks = {
  ...React,
  useState(initial) { const i = cursor++; if (!(i in slots)) slots[i] = typeof initial === 'function' ? initial() : initial; return [slots[i], value => { slots[i] = typeof value === 'function' ? value(slots[i]) : value }] },
  useRef(initial) { const i = cursor++; if (!(i in slots)) slots[i] = { current: initial }; return slots[i] },
  useEffect(fn, deps) { const i = cursor++; if (!(i in slots) || deps.some((v, j) => v !== slots[i][j])) { slots[i] = deps; effects.push(fn) } }
}
const module = { exports: {} }
const icons = new Proxy({}, { get: () => () => null })
const fixtureRequire = id => id === 'react' ? hooks : id === '@phosphor-icons/react' ? icons : require(id)
new Function('require', 'module', 'exports', result.outputFiles[0].text)(fixtureRequire, module, module.exports)
const Page = module.exports.default
function render() { cursor = 0; return Page({ id: 45 }) }
function elements(tree) {
  if (!tree || typeof tree !== 'object') return []
  if (Array.isArray(tree)) return tree.flatMap(elements)
  return [tree, ...elements(tree.props?.children)]
}
function get(tree, predicate) { const found = elements(tree).find(predicate); assert.ok(found, 'expected form element'); return found }
function byId(tree, id) { return get(tree, el => el.props?.id === id) }
function manualSelector(tree) { return get(tree, el => el.props?.municipalityId === 'request-municipality') }
render(); for (const effect of effects.splice(0)) effect()
await new Promise(resolve => setImmediate(resolve))
let tree = render()
assert.equal(byId(tree, 'directory-facility-address').props.readOnly, true)
assert.equal(byId(tree, 'directory-facility-address').props.value, 'Saved barangay, City of Balanga')
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes[0].payload.location_id, 20)
assert.equal(writes[0].payload.hospital_id, 1)

byId(tree, 'facility_name').props.onChange(incomplete.name); tree = render()
assert.equal(manualSelector(tree).props.municipalityCode, 'mariveles')
assert.equal(manualSelector(tree).props.barangayCode, null)
assert.equal(manualSelector(tree).props.barangayRequired, false)
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes.at(-1).payload.location_id, 10, 'hospital municipality works without barangay')
assert.equal(writes.at(-1).payload.hospital_id, 2)

byId(tree, 'facility_name').props.onChange('Unlisted hospital'); tree = render()
assert.equal(manualSelector(tree).props.municipalityCode, null)
assert.equal(manualSelector(tree).props.barangayCode, null)
const count = writes.length
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes.length, count, 'missing manual address blocks submit')
tree = render()
assert.equal(manualSelector(tree).props.barangayRequired, false)
manualSelector(tree).props.onChange({ location_id: 31, municipality_code: 'manual-city', barangay_code: null }); tree = render()
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes.at(-1).payload.location_id, 31)
assert.equal(writes.at(-1).payload.hospital_id, null)
assert.equal(writes.at(-1).payload.destination_type, undefined)
assert.equal(elements(tree).filter(el => el.type === 'input' && el.props.type === 'radio').length, 2, 'two venue radio options')
assert.equal(get(tree, el => el.type === 'input' && el.props.value === 'hospital').props.checked, true)
get(tree, el => el.type === 'input' && el.props.value === 'other').props.onChange(); tree = render()
assert.equal(get(tree, el => el.type === 'input' && el.props.value === 'other').props.checked, true)
assert.equal(get(tree, el => el.type === 'input' && el.props.value === 'hospital').props.checked, false)
assert.ok(!elements(tree).some(el => el.props?.id === 'facility_name'), 'other location has no place-name or hospital textbox')
assert.equal(manualSelector(tree).props.municipalityCode, null, 'switch clears previous address')
const beforeOther = writes.length
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes.length, beforeOther, 'other location still requires municipality')
tree = render()
assert.equal(manualSelector(tree).props.barangayRequired, false)
manualSelector(tree).props.onChange({ location_id: 41, municipality_code: 'other-city', barangay_code: null }); tree = render()
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes.at(-1).payload.facility_name, 'Other donation venue')
assert.equal(writes.at(-1).payload.hospital_id, null)
assert.equal(writes.at(-1).payload.location_id, 41)
assert.equal(writes.at(-1).payload.location_mode, undefined, 'UI mode does not introduce a database field')
get(tree, el => el.type === 'input' && el.props.value === 'hospital').props.onChange(); tree = render()
assert.equal(get(tree, el => el.type === 'input' && el.props.value === 'hospital').props.checked, true)
assert.equal(byId(tree, 'facility_name').props.value, '')
assert.equal(manualSelector(tree).props.municipalityCode, null)
byId(tree, 'facility_name').props.onChange(complete.name); tree = render()
assert.equal(byId(tree, 'directory-facility-address').props.readOnly, true)
await get(tree, el => el.type === 'form').props.onSubmit({ preventDefault() {} })
assert.equal(writes.at(-1).payload.location_id, 20)
const source = readFileSync(new URL('../frontend/src/pages/RequestFormPage.jsx', import.meta.url), 'utf8')
assert.ok(source.includes('Other donation venue') && !source.includes('Meeting place / landmark'))
assert.ok(!source.includes('This hospital has an incomplete directory address.'))
console.log('Form component checks passed: two venue radios, selection state, no meeting-place textbox, optional barangay and stale-value clearing (mocked hooks/API; no browser).')
