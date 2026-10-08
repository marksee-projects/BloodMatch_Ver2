export function normalizeRejectionReason(value) {
  return typeof value === 'string' ? value.replace(/^[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+$/gu, '') : ''
}

export function rejectionReasonError(value) {
  const reason = normalizeRejectionReason(value)
  if (!/[^\s\p{Z}\p{Cf}]/u.test(reason)) return 'Enter a rejection reason.'
  if (Array.from(reason).length > 500) return 'Rejection reason must be at most 500 characters.'
  return ''
}
