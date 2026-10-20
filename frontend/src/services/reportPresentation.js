const labels = {
  submitted: 'Report submitted', requester_responded: 'Requester responded',
  mutually_acknowledged: 'Donation mutually acknowledged', admin_confirmed: 'Donation confirmed by admin',
  reopened_for_clarification: 'Report reopened for clarification', clarification_replied: 'Clarification provided',
  clarification_requested: 'Clarification requested', rejected: 'Report rejected',
  version_superseded: 'Earlier submission preserved', revised: 'Report details corrected',
  hold_released_no_donation: 'Report hold released', completed_correction_requested: 'Correction requested',
  completed_correction_response: 'Staff responded to correction', requester_reminded: 'Requester reminded',
  escalated: 'Report sent for admin review', proof_updated: 'Supporting photos updated',
}
export function reportEventLabel(type) { return labels[type] || 'Report updated' }
export function reportSnapshot(value) {
  try { const parsed = typeof value === 'string' ? JSON.parse(value) : value; return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {} }
  catch { return {} }
}
export function requesterResponseLabel(value) {
  return ({ received: 'Donation received', did_not_donate: 'Donation did not happen', not_sure: 'Not sure' })[value] || 'No acknowledgment recorded'
}
