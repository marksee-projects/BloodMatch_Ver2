let csrfToken = null
let csrfPromise = null

async function fetchCsrfWithRetry(retries = 3, baseDelay = 400) {
  let lastError = null

  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      // Primary endpoint
      const res = await fetch('/api/csrf', { credentials: 'include' })
      if (res.ok) {
        const body = await res.json().catch(() => null)
        if (body?.data?.csrf_token) {
          return body.data.csrf_token
        }
      }

      // Try alternate endpoint /api/csrf-token on failure
      const altRes = await fetch('/api/csrf-token', { credentials: 'include' }).catch(() => null)
      if (altRes && altRes.ok) {
        const body = await altRes.json().catch(() => null)
        if (body?.data?.csrf_token) {
          return body.data.csrf_token
        }
      }

      lastError = new Error(`CSRF server responded with status ${res.status}`)
    } catch (err) {
      lastError = err
    }

    if (attempt < retries) {
      // Exponential backoff wait before retry
      await new Promise((resolve) => setTimeout(resolve, baseDelay * attempt))
    }
  }

  throw new Error(
    'Unable to connect to BloodMatch server. Please ensure the backend server is running and try again.'
  )
}

export async function ensureCsrf() {
  if (csrfToken) return csrfToken

  if (!csrfPromise) {
    csrfPromise = fetchCsrfWithRetry()
      .then((token) => {
        csrfToken = token
        return token
      })
      .finally(() => {
        csrfPromise = null
      })
  }

  return csrfPromise
}

export function clearCsrf() {
  csrfToken = null
  csrfPromise = null
}

async function request(path, options = {}, isRetry = false) {
  const method = options.method || 'GET'

  if (method !== 'GET') {
    const token = await ensureCsrf()
    options.headers = {
      'X-CSRF-Token': token,
      ...(options.headers || {})
    }
  }

  const headers = { ...(options.headers || {}) }
  if (!(options.body instanceof FormData) && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json'
  }

  const res = await fetch(path, {
    credentials: 'include',
    headers,
    ...options
  })

  const contentType = res.headers.get('content-type') || ''
  const body = contentType.includes('application/json') ? await res.json().catch(() => null) : null

  // If a mutating request fails with 403 (e.g. expired or invalid CSRF), retry once with a freshly fetched token
  if (res.status === 403 && !isRetry && method !== 'GET') {
    const message = body?.error?.message || ''
    if (message.toLowerCase().includes('csrf')) {
      clearCsrf()
      return request(path, options, true)
    }
  }

  if (!res.ok) {
    const message = body?.error?.message || `Request failed (${res.status})`
    const error = new Error(message)
    error.status = res.status
    error.details = body?.error?.details || {}
    throw error
  }

  return body?.data ?? null
}

export const api = {
  get: (path, options = {}) => request(path, { ...options, method: 'GET' }),
  post: (path, data) =>
    request(path, { method: 'POST', body: JSON.stringify(data ?? {}) }),
  postForm: (path, formData) =>
    request(path, { method: 'POST', body: formData }),
  put: (path, data) => request(path, { method: 'PUT', body: JSON.stringify(data ?? {}) }),
  delete: (path) => request(path, { method: 'DELETE' }),
  upload: async (path, file, fields = {}) => {
    const token = await ensureCsrf()
    const form = new FormData()
    form.append('file', file)
    Object.entries(fields).forEach(([k, v]) => form.append(k, v))
    const res = await fetch(path, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-CSRF-Token': token },
      body: form
    })
    const contentType = res.headers.get('content-type') || ''
    const body = contentType.includes('application/json') ? await res.json().catch(() => null) : null
    if (!res.ok) {
      const message = body?.error?.message || `Upload failed (${res.status})`
      const error = new Error(message)
      error.status = res.status
      error.details = body?.error?.details || {}
      throw error
    }
    return body?.data ?? null
  }
}
