const overviewFields = ['total_users', 'total_members', 'total_staff', 'active_open_requests', 'open_units_needed', 'fulfilled_requests', 'cancelled_requests', 'expired_requests', 'resolved_requests', 'fulfillment_rate_percent', 'cancellation_rate_percent', 'expiration_rate_percent', 'pending_verifications', 'pending_donation_reports', 'enrolled_donors', 'available_donors']
const poolFields = ['total_enrolled', 'available', 'standby', 'cooldown', 'unavailable']

export function parseAdminDashboard(data) {
  for (const [object, fields] of [[data?.overview, overviewFields], [data?.donor_pool, poolFields]]) {
    if (!object || fields.some(key => typeof object[key] !== 'number' || !Number.isFinite(object[key]) || object[key] < 0 || (key.endsWith('_percent') && object[key] > 100))) {
      throw new Error('Dashboard metrics could not be read. Please retry.')
    }
  }
  return data
}
