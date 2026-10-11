const supported = new Set(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])

export async function loadDocumentPreview(document, signal, fetcher = fetch) {
  if (!Number.isSafeInteger(document.id) || document.id <= 0 || !supported.has(document.mime_type)) {
    throw new Error('Preview is unavailable for this document format.')
  }
  const response = await fetcher(`/api/admin/documents/${document.id}/file`, {
    credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal,
  })
  if (!response.ok) throw new Error('Document could not be loaded. Check your access and try again.')
  const mime = (response.headers.get('content-type') || '').split(';')[0].trim().toLowerCase()
  if (!supported.has(mime) || mime !== document.mime_type) throw new Error('Document format could not be verified.')
  return response.blob()
}
