import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/apiClient'
import { Button } from './ui/Button'
export function useDonationOfferStatus(matchId) {
  const { user } = useAuth()
  return useQuery({ queryKey: ['donation-offer-status', matchId, user?.id], queryFn: () => api.get(`/api/donation-offers/${matchId}/context`), enabled: Boolean(matchId), refetchInterval: 5000, retry: false })
}
export function DonationReportButton({ matchId, onClick, size = 'md' }) {
  const { data, error } = useDonationOfferStatus(matchId)
  const submitted = Boolean(data?.report_id)
  const canReport = !error && data?.status === 'accepted' && !submitted
  const reason = submitted ? 'You already submitted a report. Open View donation report to track or correct it.' : 'Wait for the requester to accept your donor offer before reporting a donation.'
  return <Button size={size} variant={canReport ? 'primary' : 'secondary'} disabled={!canReport} title={canReport ? undefined : reason} style={canReport ? undefined : { backgroundColor: 'var(--color-surface-sunken)', color: 'var(--color-text-muted)' }} onClick={canReport ? onClick : undefined}>{submitted ? 'Report submitted' : 'I donated'}</Button>
}
export default function DonationOfferStatus({ matchId }) {
  const { data, error } = useDonationOfferStatus(matchId)
  if (error) return <span role="alert">{error.message}</span>
  return <>{data?.conversation_id ? <Button size="sm" variant="secondary" to={`/chat?conversation=${data.conversation_id}&request=${data.request_id}`}>Open request chat</Button> : <small>{data?.status === 'accepted' ? 'Chat unavailable under current account restrictions' : data?.status === 'inactive' ? 'Coordination inactive · report history remains available' : 'Awaiting requester acceptance'}</small>}{data?.report_id && <Button size="sm" variant="secondary" to={`/donation-reports?report=${data.report_id}`}>View donation report</Button>}</>
}
