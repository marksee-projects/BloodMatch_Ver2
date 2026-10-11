/**
 * Shared notification helpers used by the navbar flyout and the
 * Notifications page fallback. Business semantics live here once so both
 * surfaces resolve the same deep links and timestamps.
 */

/** Resolve the in-app destination for a notification, if it is actionable. */
export function notificationDisplayText(notification, field) {
  const text = String(notification?.[field] || '')
  if (!['blood_request', 'donation_report'].includes(notification?.related_type)) return text
  // Presentation only for legacy rows: keep stored IDs, links and read state intact.
  return text.replace(/\b(blood request|request)\s+#\d+\b/gi, '$1')
    .replace(/(compatible donation opportunity)\s+\(#\d+\)/gi, '$1')
}

export function notificationDeepLink(n, role = 'admin') {
  if (!n) return null
  if (n.related_type === 'blood_request' && n.related_id) {
    return `/requests/${n.related_id}/matches`
  }
  if (n.type === 'verification.requested' && n.related_id) {
    return role === 'admin' ? `/admin/verifications?user=${n.related_id}` : null
  }
  if (n.type === 'verification.decision') return '/profile'
  if (n.type === 'verification.resubmitted') return '/profile'
  if (n.type === 'account.status_changed') return '/profile'
  if (n.type === 'donation.confirmed' || n.type === 'donation.rejected') return '/profile'
  if (n.type === 'donation.reported' && role === 'admin') return '/admin/confirmations'
  return null
}

/** Format a `YYYY-MM-DD HH:MM:SS` UTC timestamp for display. */
export function formatNotificationTime(createdAt) {
  if (!createdAt) return ''
  try {
    return new Date(String(createdAt).replace(' ', 'T') + 'Z').toLocaleString()
  } catch {
    return String(createdAt)
  }
}
