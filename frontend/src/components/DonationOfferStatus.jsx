import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/apiClient'
import { Button } from './ui/Button'
export default function DonationOfferStatus({ matchId }) {
  const { user } = useAuth()
  const { data, error } = useQuery({ queryKey: ['donation-offer-status', matchId, user?.id], queryFn: () => api.get(`/api/donation-offers/${matchId}/context`), enabled: Boolean(matchId), refetchInterval: 5000, retry: false })
  if (error) return <span role="alert">{error.message}</span>
  return <>{data?.conversation_id ? <Button size="sm" variant="secondary" to={`/chat?conversation=${data.conversation_id}&request=${data.request_id}`}>Open request chat</Button> : <small>{data?.status === 'accepted' ? 'Chat unavailable under current account restrictions' : data?.status === 'inactive' ? 'Coordination inactive · report history remains available' : 'Awaiting requester acceptance'}</small>}{data?.report_id && <Button size="sm" variant="secondary" to={`/donation-reports?report=${data.report_id}`}>View donation report</Button>}</>
}
