export function documentLabel(document) {
  if (document.doc_type !== 'national_id') return 'Document'
  return ['front', 'back'].includes(document.document_side) ? `National ID — ${document.document_side}` : 'National ID (side not specified)'
}
