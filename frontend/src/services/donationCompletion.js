export const DONATION_TIMEZONE = 'Asia/Manila'

// The picker is explicitly Manila time regardless of the browser's timezone.
export function donationTimeToUtc(value) {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value || '')) throw new Error('Choose the actual donation date and time.')
  const date = new Date(`${value}:00+08:00`)
  if (!Number.isFinite(date.getTime())) throw new Error('Choose a valid donation date and time.')
  if (new Date(date.getTime() + 8 * 3600000).toISOString().slice(0, 16) !== value) throw new Error('Choose a valid calendar date and time.')
  return date.toISOString().replace('.000Z', 'Z')
}

export function donationTimeForPicker(value) {
  if (!value) return ''
  const date = new Date(value.replace(' ', 'T') + 'Z')
  return new Date(date.getTime() + 8 * 3600000).toISOString().slice(0, 16)
}

export function donationStatus(report) {
  if (report.status === 'CONFIRMED') return report.completion_basis === 'mutual' ? 'Mutually acknowledged donation' : report.completion_basis === 'legacy_admin' ? 'Previously confirmed by admin' : 'Admin-confirmed donation'
  if (report.status === 'REJECTED') return 'Rejected report'
  if (report.workflow_state === 'clarification') return 'Clarification requested'
  if (report.requester_response === 'did_not_donate') return 'Disputed — admin review'
  if (report.requester_response === 'not_sure') return 'Uncertain — admin review'
  if (report.workflow_state === 'needs_review') return 'Pending admin review'
  return 'Pending requester acknowledgment'
}
