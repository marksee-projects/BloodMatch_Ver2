export function notificationInstant(value) {
  if (!value) return NaN
  const text = String(value).replace(' ', 'T')
  return Date.parse(/(?:Z|[+-]\d\d:\d\d)$/.test(text) ? text : `${text}Z`)
}

export function liveNotificationKind(notice, { baseline, now, viewing = false }) {
  const created = notificationInstant(notice.created_at)
  if (viewing || notice.read_at || !Number.isFinite(created) || !Number.isFinite(baseline) || !Number.isFinite(now) || created <= baseline || created > now || now - created > 60000) return null
  if (notice.type === 'match.new') return notice.related_request_status === 'OPEN' && notice.related_request_urgency === 'emergency' ? 'emergency' : null
  return ['offer.accepted','offer.donation_prompt','donation.awaiting_acknowledgment','donation.clarification_requested'].includes(notice.type) ? 'action' : null
}

export function canClaimNotification(state, id, kind, now) {
  if ((state.ids || []).includes(String(id))) return false
  return !state.at || now - state.at >= 30000 || (kind === 'emergency' && state.kind !== 'emergency')
}

// Web Locks serializes tabs; storage contains only account-scoped event IDs/times.
// If coordination is unavailable, keep the event in the bell instead of risking duplicates.
export async function claimLiveNotification(accountId, id, kind) {
  if (!globalThis.navigator?.locks?.request) return false
  try {
    return await navigator.locks.request(`bloodmatch-notices-${accountId}`, () => {
      const key = `bloodmatch:live-notices:${accountId}`
      const state = JSON.parse(localStorage.getItem(key) || '{}')
      const now = Date.now()
      if (!canClaimNotification(state, id, kind, now)) return false
      localStorage.setItem(key, JSON.stringify({ at: now, kind, ids: [...(state.ids || []), String(id)].slice(-100) }))
      return true
    })
  } catch { return false }
}
