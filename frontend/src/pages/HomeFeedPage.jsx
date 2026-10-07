import React from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { CaretDown, MapPin } from '@phosphor-icons/react'
import { useAuth } from '../context/AuthContext'
import { Card } from '../components/ui/Card'
import { Button } from '../components/ui/Button'
import { FeedCard } from '../components/FeedCard'
import DemandMapWidget from '../components/DemandMapWidget'
import EmailVerificationDialog from '../components/EmailVerificationDialog'
import { api } from '../services/apiClient'
import styles from './HomeFeedPage.module.css'

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const URGENCIES = ['routine', 'urgent', 'emergency']
const titleCase = (value) => value.charAt(0).toUpperCase() + value.slice(1)
const readList = (params, key) => [...new Set(params.getAll(key).flatMap((value) => value.split(',')).filter(Boolean))]

export default function HomeFeedPage() {
  const { user, refresh } = useAuth()
  const municipalityRef = React.useRef(null)
  const [searchParams, setSearchParams] = useSearchParams()
  const municipalityCode = searchParams.get('municipality_code') || ''
  const blood = readList(searchParams, 'blood')
  const urgency = readList(searchParams, 'urgency')
  const rawPage = searchParams.get('page') || '1'
  const visiblePages = /^[1-9]\d*$/.test(rawPage) && Number(rawPage) <= 2147483647 ? Number(rawPage) : 1
  const filterParams = new URLSearchParams()
  if (municipalityCode) filterParams.set('municipality_code', municipalityCode)
  if (blood.length) filterParams.set('blood', blood.join(','))
  if (urgency.length) filterParams.set('urgency', urgency.join(','))
  const filterKey = filterParams.toString()
  const hasFilters = Boolean(filterKey)
  const updateFilter = (key, values) => {
    const next = new URLSearchParams(filterKey)
    if (values.length) next.set(key, values.join(','))
    else next.delete(key)
    setSearchParams(next)
  }
  const clearFilters = () => {
    setSearchParams(new URLSearchParams())
    municipalityRef.current?.focus()
  }
  const [verifyDialogOpen, setVerifyDialogOpen] = React.useState(false)
  const [demandExpanded, setDemandExpanded] = React.useState(false)
  const { data, isPending: feedLoading, error: feedError, refetch: refetchFeed,
    fetchNextPage, hasNextPage, isFetchingNextPage } = useInfiniteQuery({
    queryKey: ['home-feed', user?.id, user?.blood_type, user?.availability, user?.donor_enrolled,
      user?.verification_status, user?.email_verified, filterKey],
    initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => {
      const params = new URLSearchParams(filterKey)
      params.set('page', String(pageParam))
      return api.get(`/api/home-feed?${params}`, { signal })
    },
    getNextPageParam: (lastPage) => lastPage.has_more ? lastPage.page + 1 : undefined,
    enabled: Boolean(user),
    retry: false
  })
  const loadedPageCount = data?.pages.length || 0
  // Restore a shared Load more URL sequentially; Back hides cached later pages.
  React.useEffect(() => {
    if (loadedPageCount < visiblePages && hasNextPage && !isFetchingNextPage && !feedError) fetchNextPage()
  }, [loadedPageCount, visiblePages, hasNextPage, isFetchingNextPage, feedError, fetchNextPage])
  const shownPages = data?.pages.slice(0, visiblePages) || []
  const feedData = shownPages[0]
  const requests = [...new Map(shownPages.flatMap((page) => page.requests).map((request) => [request.id, request])).values()]
  const lastShownPage = shownPages.at(-1)
  const { data: locationData, error: locationError, refetch: refetchLocations } = useQuery({
    queryKey: ['home-municipalities'],
    queryFn: ({ signal }) => api.get('/api/locations/municipalities', { signal }),
    enabled: Boolean(user),
    retry: false
  })
  const municipalities = locationData?.municipalities || []
  const loadMore = () => {
    const next = new URLSearchParams(filterKey)
    next.set('page', String(visiblePages + 1))
    setSearchParams(next)
  }

  return (
    <div className={styles.layout}>
      {user && user.email_verified === false && (
        <div className={styles.unverifiedBanner}>
          <div className={styles.unverifiedContent}>
            <span className={styles.unverifiedText}>
              Please verify your email address to access all features.
            </span>
            <button className={styles.unverifiedButton} onClick={() => setVerifyDialogOpen(true)}>
              Verify Now
            </button>
          </div>
        </div>
      )}

      <section className={styles.feedLayout}>
        <aside className={styles.leftColumn}>
          <Card>
            <div className={styles.profileRow}>
              <div className={styles.profileAvatarWrapper}>
                {user?.profile_picture_url ? (
                  <img src={user.profile_picture_url} alt="Profile" className={styles.profileAvatar} />
                ) : (
                  <div className={styles.profileAvatar} />
                )}
                {user?.availability === 'available' && (
                  <div className={styles.statusIndicator} title="Available donor" />
                )}
              </div>
              <div className={styles.profileInfo}>
                <span className={styles.profileName}>{user?.full_name || 'User'}</span>
                <span className={styles.profileRole}>{user?.role || 'Member'}</span>
              </div>
            </div>

            <hr style={{ margin: 'var(--space-3) 0', border: 'none', borderTop: '1px solid var(--color-border-hairline)' }} />

            <details className={styles.quickLinksDetails}>
              <summary>Account Details</summary>
              <div style={{ marginTop: 'var(--space-3)' }}>
                <div className={styles.accountDetailRow}>
                  <span className={styles.accountDetailLabel}>Blood Type</span>
                  <span className={styles.accountDetailValue}>{user?.blood_type || 'Unknown'}</span>
                </div>
                <div className={styles.accountDetailRow}>
                  <span className={styles.accountDetailLabel}>Status</span>
                  <span className={styles.accountDetailValue}>{user?.verification_status === 'verified' ? 'Verified' : 'Pending Verification'}</span>
                </div>
                <div className={styles.accountDetailRow}>
                  <span className={styles.accountDetailLabel}>Chapter</span>
                  <span className={styles.accountDetailValue}>{user?.chapter_name || 'N/A'}</span>
                </div>
                <hr className={styles.accountDetailDivider} />
                <ul className={styles.quickLinks}>
                  <li><Link to="/profile" className={styles.quickLink}>My Requests</Link></li>
                  <li><Link to="/profile" className={styles.quickLink}>Edit Profile</Link></li>
                </ul>
              </div>
            </details>
          </Card>
        </aside>

        <main className={styles.centerColumn}>
          <Card className={styles.filters}>
            <div className={styles.filterHeader}>
              <button type="button" className={styles.clearFilters} onClick={clearFilters}>Reset filters</button>
              <h1 className={styles.filterHeading}>Compatible requests</h1>
            </div>
            <div className={styles.filterRow}>
              <label className={`${styles.selectFilter} ${styles.municipalityFilter}`}>
                <span className={styles.visuallyHidden}>Municipality</span>
                <select ref={municipalityRef} value={municipalityCode} onChange={(event) => updateFilter('municipality_code', event.target.value ? [event.target.value] : [])}>
                  <option value="">All municipalities</option>
                  {municipalityCode && !municipalities.some((item) => item.psgc_code === municipalityCode) && (
                    <option value={municipalityCode}>{municipalityCode}</option>
                  )}
                  {municipalities.map((item) => <option key={item.psgc_code} value={item.psgc_code}>{item.name}</option>)}
                </select>
                <CaretDown className={styles.selectCaret} size={16} aria-hidden="true" />
              </label>
              <label className={styles.selectFilter}>
                <span className={styles.visuallyHidden}>Blood type</span>
                <select value={blood.join(',')} onChange={(event) => updateFilter('blood', event.target.value ? [event.target.value] : [])}>
                  <option value="">All blood types</option>
                  {blood.length > 1 && <option value={blood.join(',')}>{blood.join(', ')}</option>}
                  {BLOOD_TYPES.map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
                <CaretDown className={styles.selectCaret} size={16} aria-hidden="true" />
              </label>
              <label className={styles.selectFilter}>
                <span className={styles.visuallyHidden}>Urgency</span>
                <select value={urgency.join(',')} onChange={(event) => updateFilter('urgency', event.target.value ? [event.target.value] : [])}>
                  <option value="">All urgencies</option>
                  {urgency.length > 1 && <option value={urgency.join(',')}>{urgency.map(titleCase).join(', ')}</option>}
                  {URGENCIES.map((value) => <option key={value} value={value}>{titleCase(value)}</option>)}
                </select>
                <CaretDown className={styles.selectCaret} size={16} aria-hidden="true" />
              </label>
            </div>
            {locationError && <div className={styles.locationError} role="alert">
              <span>Municipalities could not be loaded.</span>
              <Button variant="secondary" onClick={() => refetchLocations()}>Retry municipalities</Button>
            </div>}

          </Card>
          <div className={styles.contextLine} aria-live="polite">
            {feedLoading ? 'Loading compatible requests…' : feedError ? 'Requests could not be loaded.' :
              `${feedData?.total_matching || 0} compatible ${(feedData?.total_matching || 0) === 1 ? 'request' : 'requests'}${hasFilters && !feedData?.reason_code ? ` of ${feedData?.total_unfiltered || 0}` : ''}`}
          </div>

          {feedLoading && (
            <div className={styles.skeletonList} role="status" aria-label="Loading compatible requests">
              {[1, 2, 3].map((item) => <Card key={item} className={styles.skeletonCard}>
                <div className={styles.skeletonLine} aria-hidden="true" />
                <div className={styles.skeletonLineShort} aria-hidden="true" />
              </Card>)}
            </div>
          )}

          {feedError && (
            <Card padding="md" className={styles.feedState}>
              <h2 className={styles.feedStateTitle}>Requests couldn&apos;t be loaded</h2>
              <p className={styles.feedStateText}>{feedError.message}</p>
              <Button variant="secondary" onClick={() => refetchFeed()}>Retry</Button>
              {hasFilters && <Button variant="secondary" onClick={clearFilters}>Reset filters</Button>}
            </Card>
          )}

          {!feedLoading && !feedError && requests.length === 0 && (
            <Card padding="md" className={styles.feedState}>
              <h2 className={styles.feedStateTitle}>
                {feedData?.reason_code ? 'Compatible requests unavailable' :
                  hasFilters && feedData?.total_unfiltered > 0 ? 'No compatible requests fit these filters' :
                    'No compatible requests right now'}
              </h2>
              <p className={styles.feedStateText}>
                {feedData?.reason_text || (hasFilters && feedData?.total_unfiltered > 0
                  ? 'Remove a filter or clear them all to see more compatible requests.'
                  : 'There are no current open requests compatible with your blood type. Check again later.')}
              </p>
              {hasFilters && <Button variant="secondary" onClick={clearFilters}>Reset filters</Button>}
              {['blood_type_missing', 'staff_no_blood_type'].includes(feedData?.reason_code) && <Button to="/profile">Update profile</Button>}
            </Card>
          )}

          {!feedLoading && !feedError && requests.map((request) => (
            <FeedCard key={request.id} request={request} />
          ))}
          {!feedLoading && !feedError && lastShownPage?.has_more && (
            <Button variant="secondary" onClick={loadMore} disabled={isFetchingNextPage || loadedPageCount < visiblePages}>
              {isFetchingNextPage || loadedPageCount < visiblePages ? 'Loading more…' : 'Load more'}
            </Button>
          )}
        </main>

        <aside className={styles.rightColumn}>
          <Card padding="none" className={styles.demandCard}>
            <button
              type="button"
              className={styles.mapHeading}
              aria-expanded={demandExpanded}
              aria-controls="home-demand-content"
              onClick={() => setDemandExpanded((current) => !current)}
            >
              <MapPin size={20} weight="fill" aria-hidden="true" />
              <h2>Chapter Demand Map</h2>
              <CaretDown className={demandExpanded ? styles.mapCaretOpen : styles.mapCaret} size={18} aria-hidden="true" />
            </button>
            {demandExpanded && (
              <div id="home-demand-content" className={styles.demandContent}>
                <DemandMapWidget mode="mini" />
              </div>
            )}
          </Card>
        </aside>
      </section>

      <EmailVerificationDialog
        isOpen={verifyDialogOpen}
        initialEmail={user?.email}
        onClose={() => setVerifyDialogOpen(false)}
        onSuccess={() => {
          setVerifyDialogOpen(false)
          refresh()
        }}
      />
    </div>
  )
}
