import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { transform } from '../frontend/node_modules/esbuild/lib/main.js'

const require = createRequire(new URL('../frontend/package.json', import.meta.url))
const React = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
async function component(path, imports) {
  const source = await readFile(new URL(path, import.meta.url), 'utf8')
  const { code } = await transform(source.replace(/^import .*$/gm, ''), { loader: 'jsx', format: 'cjs' })
  const module = { exports: {} }
  new Function('module', 'exports', ...Object.keys(imports), code)(module, module.exports, ...Object.values(imports))
  return module.exports
}
const { Button } = await component('../frontend/src/components/ui/Button.jsx', { React, Link: 'a' })
let state
const { DonationReportButton } = await component('../frontend/src/components/DonationOfferStatus.jsx', {
  React, Button, useAuth: () => ({ user: { id: 7 } }), useQuery: () => state, api: {},
})
function render(query) {
  state = query
  return renderToStaticMarkup(React.createElement(DonationReportButton, { matchId: 91, onClick() {} }))
}
for (const query of [{}, { data: { status: 'awaiting_acceptance' } }, { data: { status: 'inactive' } }, { error: new Error('Unavailable') }, { data: { status: 'accepted' }, error: new Error('Refresh failed') }]) {
  const html = render(query)
  assert.match(html, /disabled=""/)
  assert.match(html, /Wait for the requester to accept/)
  assert.match(html, /var\(--color-surface-sunken\)/)
}
const accepted = render({ data: { status: 'accepted' } })
assert.doesNotMatch(accepted, /disabled=""/)
assert.match(accepted, /I donated/)
assert.match(accepted, /var\(--color-brand-navy\)/)
console.log('PASS donor report button renders disabled and gray while pending, loading, inactive or unavailable; enabled after acceptance. No HTTP/database.')
