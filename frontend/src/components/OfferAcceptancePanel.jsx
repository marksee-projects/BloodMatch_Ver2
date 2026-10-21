import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { useChat } from '../context/ChatContext'
import { Button } from './ui/Button'
import { Badge } from './ui/Badge'
import { User } from '@phosphor-icons/react'
import { emptyMatchFilters, filterMatches, matchChapterOptions } from '../services/matchFilters'
import { donorSections, donorPage } from '../services/donorSections'
import styles from '../pages/MatchesPage.module.css'
export default function OfferAcceptancePanel({ requestId, matches = [], onRefresh }) {
  const { user } = useAuth()
  const { canChat } = useChat()
  const navigate = useNavigate()
  const [tab, setTab] = useState('offers')
  const [filters, setFilters] = useState(emptyMatchFilters)
  const [busy, setBusy] = useState(null)
  const busyRef = useRef(false)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  const [page, setPage] = useState(1)
  const sectionRef = useRef(null)
  useEffect(() => { setPage(1) }, [requestId, tab, filters.search, filters.chapter, filters.status])
  useEffect(() => { setTab('offers'); setFilters(emptyMatchFilters); setError(''); setFeedback('') }, [requestId])
  const { data, error: loadError, isLoading, refetch } = useQuery({ queryKey: ['donation-offers', requestId, user?.id], queryFn: () => api.get(`/api/requests/${requestId}/offers`), refetchInterval: 5000, retry: false })
  const groups = donorSections(matches, data?.offers || [])
  const rows = filterMatches(groups[tab], filters)
  const paged = donorPage(rows, page)
  const chapters = matchChapterOptions([...matches, ...groups.offers])
  const chatLink = conversation => `/chat?conversation=${conversation}&request=${requestId}`
  const selectTab = value => { setTab(value); setFilters(current => ({ ...current, status: 'all' })) }
  const perform = async (row, action) => {
    if (busyRef.current) return
    busyRef.current = true; setBusy(row.match_id); setError(''); setFeedback('')
    try {
      if (action === 'accept') {
        const result = await api.post(`/api/donation-offers/${row.match_id}/accept`, { expected_cycle: Number(row.offer_cycle) })
        navigate(chatLink(result.conversation_id))
      } else {
        const result = await api.post('/api/chat/requests', { recipient_id: Number(row.profile_user_id) })
        if (result.request?.status === 'accepted' && result.request.conversation_id) navigate(chatLink(result.request.conversation_id))
        else setFeedback('Message request pending. The donor must accept before you can chat. This is not a donation offer.')
      }
    } catch (e) { setError(e.message) }
    finally { await Promise.allSettled([refetch(), onRefresh?.()]); busyRef.current = false; setBusy(null) }
  }
  return <section ref={sectionRef} className={styles.matchesSurface} aria-labelledby="donors-heading">
      <div className={styles.filterToolbar}><Button variant="secondary" onClick={() => setFilters(emptyMatchFilters)}>Reset filters</Button></div>
    <div className={styles.sectionHeading}><h2 id="donors-heading">Donors</h2><p>{tab === 'offers' ? 'These donors have offered to help with your request.' : "These donors match your request but have not offered to help."}</p></div>
    <div className={styles.donorTabs} role="tablist" aria-label="Donor lists">
      {['offers','suggestions'].map(value => <button key={value} id={`donor-tab-${value}`} type="button" role="tab" aria-selected={tab === value} aria-controls="donor-list-panel" tabIndex={tab === value ? 0 : -1}
        onClick={() => selectTab(value)} onKeyDown={e => { if (['ArrowLeft','ArrowRight','Home','End'].includes(e.key)) { e.preventDefault(); const next = e.key === 'Home' ? 'offers' : e.key === 'End' ? 'suggestions' : tab === 'offers' ? 'suggestions' : 'offers'; selectTab(next); document.getElementById(`donor-tab-${next}`)?.focus() } }}>
        {value === 'offers' ? 'Donor offers' : 'Suggested donors'} <span>{groups[value].length}</span>
      </button>)}
    </div>
    <div id="donor-list-panel" role="tabpanel" aria-labelledby={`donor-tab-${tab}`} tabIndex={0}>
      {(error || loadError) && <p role="alert">{error || loadError.message}</p>}
      {feedback && <p role="status">{feedback}</p>}
      <div className={styles.matchFilters} role="search" aria-label="Filter donors">
        <div className={styles.filterField}><label htmlFor="donor-search">Search donors</label><input id="donor-search" type="search" value={filters.search} onChange={e => setFilters(f => ({ ...f, search: e.target.value }))} placeholder="Search by donor name" /></div>
        <div className={styles.filterField}><label htmlFor="donor-status">Response status</label><select id="donor-status" value={filters.status} onChange={e => setFilters(f => ({ ...f, status: e.target.value }))}><option value="all">All statuses</option>{tab === 'offers' ? <><option value="RESPONDED">Offered</option><option value="COMPLETED">Completed</option><option value="CLOSED">Inactive</option></> : <option value="awaiting">Not offered</option>}</select></div>
        <div className={styles.filterField}><label htmlFor="donor-chapter">Chapter</label><select id="donor-chapter" value={filters.chapter} onChange={e => setFilters(f => ({ ...f, chapter: e.target.value }))}><option value="all">All chapters</option>{chapters.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}</select></div>
      </div>
      <p className={styles.filterCount} role="status">Showing {paged.start}–{paged.end} of {rows.length} {tab === 'offers' ? 'donor offers' : 'suggested donors'}{rows.length !== groups[tab].length ? ` (${groups[tab].length} before filters)` : ''}</p>
      {isLoading && <p role="status">Loading donor offers…</p>}
      {!isLoading && !rows.length && <div className={styles.emptyState}><h3>{groups[tab].length ? 'No donors match your filters' : tab === 'offers' ? 'No donor offers yet' : 'No suggested donors right now'}</h3><p>{groups[tab].length ? 'Adjust your search or reset the filters.' : tab === 'offers' ? 'Donors will appear here after selecting Offer to donate.' : 'Suggestions update when compatible donors are eligible and available.'}</p></div>}
      <div className={styles.donorCards}>{paged.rows.map(row => <article key={row.match_id} className={styles.donorCard}>
        <div className={styles.donorCardIdentity}><div className={styles.donorAvatar}><User size={22} aria-hidden="true" />{row.profile_picture_url && <img key={row.profile_picture_url} src={row.profile_picture_url} alt="" onError={e => { e.currentTarget.hidden = true }} />}</div><div className={styles.donorIdentity}><h3>{row.display_name || row.donor_name}</h3><p>{row.chapter_name || 'Chapter not assigned'}</p></div></div>
        <Badge variant={row.status === 'COMPLETED' ? 'success' : tab === 'offers' ? 'brand' : 'neutral'}>{row.status === 'COMPLETED' ? 'Completed' : tab === 'suggestions' ? 'Suggested' : row.context_id ? row.status === 'CLOSED' ? 'Accepted · inactive' : 'Accepted' : row.status === 'RESPONDED' ? 'Offered' : 'Inactive'}</Badge>
        <div className={styles.donorMetrics}><span>Preference: {row.availability || 'Not provided'}</span><span>{row.approximate_distance_km != null ? `About ${row.approximate_distance_km} km` : 'Distance not available'}</span></div>
        <div className={styles.donorCardActions}>
          {row.profile_user_id && <Button to={`/profile/${row.profile_user_id}`} variant="secondary" size="sm">View profile</Button>}
          {tab === 'offers' ? row.conversation_id ? <Button size="sm" variant="secondary" to={chatLink(row.conversation_id)}>Open chat</Button> : row.status === 'RESPONDED' ? <Button size="sm" disabled={!canChat || busy !== null || !data?.can_accept || row.offer_cycle == null} onClick={() => perform(row,'accept')}>Accept &amp; chat</Button> : <span className={styles.donorExplanation}>No active offer to accept</span>
            : row.chat?.conversation_id ? <Button size="sm" variant="secondary" to={chatLink(row.chat.conversation_id)}>Open chat</Button>
              : row.chat?.status === 'pending' ? row.chat.incoming ? <Button size="sm" variant="secondary" to="/chat">Review message request</Button> : <Button size="sm" variant="secondary" disabled>Message request pending</Button>
                : <><Button size="sm" variant="secondary" disabled={!canChat || busy !== null || !row.profile_user_id || !row.chat?.can_request} onClick={() => perform(row,'message')}>Request to message</Button>{['declined','cancelled'].includes(row.chat?.status) && <small>{row.chat.status === 'declined' ? 'Message request declined.' : 'Message request cancelled.'}{row.chat.retry_at ? ' Try again after the existing one-day waiting period.' : ''}</small>}</>}
        </div>
      </article>)}</div>
      {paged.totalPages > 1 && <nav className={styles.donorPagination} aria-label="Donor pages">
        <Button variant="secondary" disabled={paged.page === 1} onClick={() => { setPage(paged.page - 1); sectionRef.current?.scrollIntoView({ block: 'start', behavior: 'auto' }) }}>Previous</Button>
        <span aria-live="polite">Page {paged.page} of {paged.totalPages}</span>
        <Button variant="secondary" disabled={paged.page === paged.totalPages} onClick={() => { setPage(paged.page + 1); sectionRef.current?.scrollIntoView({ block: 'start', behavior: 'auto' }) }}>Next</Button>
      </nav>}
    </div>
  </section>
}
