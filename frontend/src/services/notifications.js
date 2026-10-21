/**
 * Shared notification helpers used by the navbar flyout and the
 * Notifications page fallback. Business semantics live here once so both
 * surfaces resolve the same deep links and timestamps.
 */

/** Resolve the in-app destination for a notification, if it is actionable. */
export function notificationDisplayText(notification, field) {
  const text = String(notification?.[field] || '')
  if (notification?.type === 'verification.requested' && Object.hasOwn(notification, 'related_verification_status')) {
    const status = notification.related_verification_status
    const name = notification.related_verification_name || 'This account'
    if (status === 'verified') return field === 'title' ? 'Account already verified' : `${name} is already verified.`
    if (status === 'rejected') return field === 'title' ? 'Verification reviewed' : `${name}'s verification was rejected.`
    if (!status) return field === 'title' ? 'Verification request unavailable' : 'This account is no longer available.'
    if (notification.related_verification_account_status === 'deactivated') return field === 'title' ? 'Verification request inactive' : `${name} is deactivated.`
    if (status === 'pending' && field === 'body') return `${name} uploaded a National ID and is awaiting review.`
  }
  if (!['blood_request', 'donation_report'].includes(notification?.related_type)) return text
  // Presentation only for legacy rows: keep stored IDs, links and read state intact.
  return text.replace(/\b(blood request|request)\s+#\d+\b/gi, '$1')
    .replace(/\b(donation report|donation)\s+#\d+\b/gi, '$1')
    .replace(/(compatible donation opportunity)\s+\(#\d+\)/gi, '$1')
}

export function notificationDeepLink(n, role = 'admin') {
  if (!n) return null
  if (n.type === 'offer.accepted' && n.related_conversation_id) return `/chat?conversation=${Number(n.related_conversation_id)}&request=${Number(n.related_id)}`
  if (n.related_type === 'donation_report' && n.related_id) {
    const staffReview = role === 'admin' && ['donation.reported', 'donation.review_needed', 'donation.escalated'].includes(n.type)
    return staffReview ? `/admin/confirmations?report=${n.related_id}` : `/donation-reports?report=${n.related_id}`
  }
  if (n.type === 'request.expired') return '/profile?section=expired'
  if (n.related_type === 'chat_report') return role === 'admin' ? '/admin/chat-reports' : null
  if (n.related_type === 'chat_request') return '/chat'
  if (n.type === 'donation.followup' && role === 'admin') return `/requests/${n.related_id}/matches`
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

/** Format a `YYYY-MM-DD HH:MM:SS` or ISO timestamp in Philippine time (Asia/Manila) for display. */
export function formatPhilippineDateTime(value) {
  if (!value) return ''
  try {
    const str = String(value).trim()
    const normalized = str.includes('T') ? (str.endsWith('Z') || str.includes('+') ? str : `${str}Z`) : `${str.replace(' ', 'T')}Z`
    const date = new Date(normalized)
    if (Number.isNaN(date.getTime())) return String(value)
    return date.toLocaleString('en-US', {
      timeZone: 'Asia/Manila',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    })
  } catch {
    return String(value)
  }
}

export function formatNotificationTime(createdAt) {
  return formatPhilippineDateTime(createdAt)
}

