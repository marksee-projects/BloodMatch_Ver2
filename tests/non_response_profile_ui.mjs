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
const root = fileURLToPath(new URL('../frontend/src/', import.meta.url))
const originalJs = require.extensions['.js']
function compile(module, filename) {
  const source = readFileSync(filename, 'utf8')
  const jsx = /import React(?:,| from)/.test(source) ? source : `import React from 'react';\n${source}`
  module._compile(transformSync(jsx, { loader: 'jsx', format: 'cjs' }).code, filename)
}
require.extensions['.jsx'] = compile
require.extensions['.css'] = module => { module.exports = {} }
require.extensions['.js'] = (module, filename) => {
  if (filename.endsWith('.cjs.js')) module._compile(readFileSync(filename, 'utf8'), filename)
  else if (filename.startsWith(root)) compile(module, filename)
  else originalJs(module, filename)
}
const authPath = require.resolve('./src/context/AuthContext.jsx')
require(authPath)
require.cache[authPath].exports = { ...require.cache[authPath].exports, useAuth: () => ({ user: { id: 42, role: 'member' }, refresh: async () => {} }) }
const Profile = require('./src/pages/ProfilePage.jsx').default
const originalError = console.error
console.error = (message, ...args) => {
  if (!String(message).includes('useLayoutEffect does nothing on the server')) originalError(message, ...args)
}
let checks = 0
try {
  for (const legacyHours of [42, 48]) {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    client.setQueryData(['profile', 42], { profile: { id: 42, full_name: 'Synthetic Member', role: 'member', verification_status: 'verified',
      donor_enrolled: true, donor_eligible: false, availability: 'available', blood_type: 'O+', location: {}, capabilities: {}, documents: [],
      non_response_standby: new Date(Date.now() - legacyHours * 3600000).toISOString(),
      availability_window: { blocked: true, which: 'cooldown' } }, reports: [] })
    client.setQueryData(['my-requests', 42], [])
    const markup = renderToStaticMarkup(React.createElement(QueryClientProvider, { client }, React.createElement(MemoryRouter, null, React.createElement(Profile))))
    assert(markup.includes('An unanswered opportunity passed its response deadline.'))
    assert(markup.includes('Acknowledge standby'))
    assert(markup.includes('when your availability and safety windows allow'))
    assert(!markup.includes('reached 42 hours') && !markup.includes('reached 48 hours'))
    checks += 4
    client.clear()
  }
  console.log(`${checks} mixed-legacy/new profile-copy render checks passed; no network or files changed.`)
} finally { console.error = originalError }
