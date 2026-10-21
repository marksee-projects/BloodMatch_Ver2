export function requestSavedNotice(result, editing = false, urgency) {
  const failedMatching = result?.matching_status === 'failed_retry_required'
  const emergency = (result?.request?.urgency || urgency) === 'emergency'
  return {
    severity: failedMatching ? 'warning' : 'success',
    title: failedMatching ? 'Request saved; matching needs attention' : emergency ? (editing ? 'Emergency request updated' : 'Emergency request posted') : (editing ? 'Request updated' : 'Request posted'),
    message: failedMatching
      ? 'Your request is saved, but matching failed. Ask an Admin to retry matching. Do not post a duplicate request.'
      : editing ? 'Your request details have been saved.' : 'Matching completed. Open the request to review available donor matches.',
    action: result?.request?.id ? { to: `/requests/${result.request.id}/matches`, label: 'View request' } : undefined,
  }
}
