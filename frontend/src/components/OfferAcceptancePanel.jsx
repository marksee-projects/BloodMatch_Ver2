import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { Button } from './ui/Button'
import styles from './DonationContextCard.module.css'
export default function OfferAcceptancePanel({ requestId }) {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const { data, error: loadError, refetch } = useQuery({ queryKey: ['donation-offers', requestId, user?.id], queryFn: () => api.get(`/api/requests/${requestId}/offers`), refetchInterval: 5000, retry: false })
  const accept = async offer => {
    if (busy) return
    setBusy(true); setError('')
    try { const result = await api.post(`/api/donation-offers/${offer.match_id}/accept`, { expected_cycle: Number(offer.offer_cycle) }); navigate(`/chat?conversation=${result.conversation_id}&request=${requestId}`) }
    catch (e) { setError(e.message); await refetch() } finally { setBusy(false) }
  }
  return <section className={styles.card} aria-label="Donor offers"><h2>Donor offers</h2><p>Accept an offer to coordinate privately. Acceptance reserves no units and does not confirm donation.</p>
    {(error || loadError) && <p role="alert">{error || loadError.message}</p>}
    {data?.offers.length === 0 && <p>No donor offers yet.</p>}
    {data?.offers.map(offer => <div className={styles.offer} key={offer.match_id}><strong>{offer.donor_name}</strong>{offer.conversation_id ? <Button size="sm" variant="secondary" to={`/chat?conversation=${offer.conversation_id}&request=${requestId}`}>Open request chat</Button> : <Button size="sm" disabled={busy || !data.can_accept || offer.status !== 'RESPONDED'} onClick={() => accept(offer)}>Accept &amp; chat</Button>}</div>)}
  </section>
}
