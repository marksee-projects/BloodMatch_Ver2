import { claimLiveNotification, liveNotificationKind, notificationInstant } from './services/notificationDelivery'
import ChatReportsView from './pages/admin/views/ChatReportsView'
import ChatPage from './pages/ChatPage'
import { useEffect, useState, useRef } from 'react'
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { CaretDown, CaretRight, Circle, Moon, Plus, Sun, User, Users, HandHeart, House, ClipboardText, ShieldCheck, SignOut, CircleNotch, FileText } from '@phosphor-icons/react'
import { Divide as Hamburger } from 'hamburger-react'
import { useAuth } from './context/AuthContext'
import { useQueryClient } from '@tanstack/react-query'
import { useTheme } from './context/ThemeContext'
import { clearCsrf } from './services/apiClient'
import RegisterPage from './pages/RegisterPage'
import ProfilePage from './pages/ProfilePage'
import { CreateRequestEntry, RequestCreationProvider, useRequestCreation } from './context/RequestCreationContext'
import MatchesPage from './pages/MatchesPage'
import MyResponsesPage from './pages/MyResponsesPage'
import DonationReportsPage from './pages/DonationReportsPage'
import NotificationFlyout from './components/NotificationFlyout'
import { notificationReadRevision } from './services/notificationReadState'
import MessengerFlyout from './components/chat/MessengerFlyout'
import FloatingChatContainer from './components/chat/FloatingChatContainer'
import Footer from './components/Footer'
import NotificationsPage from './pages/NotificationsPage'
import AdminLayout from './pages/admin/AdminLayout'
import DashboardView from './pages/admin/views/DashboardView'
import DemandMapView from './pages/admin/views/DemandMapView'
import AnalyticsView from './pages/admin/views/AnalyticsView'
import AuditLogsView from './pages/admin/views/AuditLogsView'
import UsersView from './pages/admin/views/UsersView'
import AdminVerificationsView from './pages/admin/views/VerificationsView'
import ConfirmationsView from './pages/admin/views/ConfirmationsView'
import DemandMapWidget from './components/DemandMapWidget'
import { api } from './services/apiClient'
import ConfirmationDialog from './components/ConfirmationDialog'
import DeleteAccount from './components/DeleteAccount'
import { useActionNoticeState, useNotify } from './context/NotificationContext'
import { notificationDeepLink, notificationDisplayText, formatNotificationTime } from './services/notifications'
import LandingPage from './pages/LandingPage'
import HomeFeedPage from './pages/HomeFeedPage'

function RequireAuth({ children, roles }) {
  const { user, loading } = useAuth()
  if (loading) {
    return (
      <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
        <p className="muted">Loading session…</p>
      </div>
    )
  }
  if (!user) {
    return <Navigate to="/" replace />
  }
  if (roles && (!roles.includes(user.role) || user.account_status !== 'active')) {
    return <Navigate to="/" replace />
  }
  return children
}

function NavbarAvatar({ src, alt, className }) {
  const [failed, setFailed] = useState(false)

  useEffect(() => { setFailed(false) }, [src])

  if (!src || failed) {
    return (
      <span className={`avatar avatar-fallback ${className || ''}`} role="img" aria-label="No profile picture">
        <User size={22} weight="regular" aria-hidden="true" />
      </span>
    )
  }
  return <img className={`avatar ${className || ''}`} src={src} alt={alt} onError={() => setFailed(true)} />
}

export default function App() {
  return <RequestCreationProvider><AppShell /></RequestCreationProvider>
}

function AppShell() {
  const notify = useNotify()
  const [logoutConfirmOpen, setLogoutConfirmOpen] = useState(false)
  const [deleteAccountOpen, setDeleteAccountOpen] = useState(false)
  const deleteReturnFocusRef = useRef(null)
  const openCreateRequest = useRequestCreation()
  const { theme, toggleTheme } = useTheme()
  const { user, loading, logout, refresh } = useAuth()
  const queryClient = useQueryClient()
  const [unread, setUnread] = useState(0)
  useEffect(() => {
    const onRead = event => {
      if (typeof event.detail?.unreadCount === 'number') setUnread(event.detail.unreadCount)
    }
    window.addEventListener('bloodmatch:notifications-read', onRead)
    return () => window.removeEventListener('bloodmatch:notifications-read', onRead)
  }, [])
  const location = useLocation()
  const navigate = useNavigate()
  const [mobileOpen, setMobileOpen] = useState(false)
  const [profileMenuOpen, setProfileMenuOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [availabilityUpdating, setAvailabilityUpdating] = useState(false)
  const [availabilityWiggle, setAvailabilityWiggle] = useState(0)
  const [availabilityError, setAvailabilityError] = useActionNoticeState(null, 'error')
  const profileMenuRef = useRef(null)
  const openDeleteAccount = event => {
    deleteReturnFocusRef.current = event.currentTarget.closest('#mobile-panel')
      ? document.querySelector('.hamburger [role="button"], .hamburger button')
      : profileMenuRef.current?.querySelector('.nav-avatar-trigger')
    setProfileMenuOpen(false); setMobileOpen(false); setDeleteAccountOpen(true)
  }
  const closeDeleteAccount = () => {
    setDeleteAccountOpen(false)
    window.requestAnimationFrame(() => deleteReturnFocusRef.current?.focus())
  }

  useEffect(() => {
    if (!profileMenuOpen) return undefined
    const onPointerDown = (e) => {
      if (profileMenuRef.current && !profileMenuRef.current.contains(e.target)) {
        setProfileMenuOpen(false)
      }
    }
    document.addEventListener('mousedown', onPointerDown)
    return () => document.removeEventListener('mousedown', onPointerDown)
  }, [profileMenuOpen])

  useEffect(() => {
    if (!user) {
      setUnread(0)
      return undefined
    }
    let active = true
    let timeoutId = null
    let isFetching = false
    let baseline = null
    const seen = new Set()
    const inCurrentView = notice => {
      const selector = notice.related_type === 'donation_report'
        ? `[data-donation-report="${Number(notice.related_id)}"], #donation-report-${Number(notice.related_id)}`
        : notice.related_type === 'blood_request' ? `[data-donation-request="${Number(notice.related_id)}"]` : null
      return selector ? [...document.querySelectorAll(selector)].some(element => { const rect = element.getBoundingClientRect(); return rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight }) : false
    }
    const fetchUnread = async () => {
      if (!active) return
      if (document.visibilityState !== 'visible') { baseline = null; scheduleNext(); return }
      if (isFetching) return
      isFetching = true
      try {
        const readRevision = notificationReadRevision()
        const d = await api.get('/api/notifications/unread-count')
        if (!active || readRevision !== notificationReadRevision() || typeof d?.unread_count !== 'number') return
        setUnread(d.unread_count)
        const now = notificationInstant(d.server_time_utc)
        const initial = baseline === null
        // Initialize even when there are zero unread items; don't replay on reconnect.
        if (initial) baseline = Number.isFinite(now) ? now : Date.now()
        if (d.unread_count <= 0) return
        const latest = await api.get('/api/notifications?read=unread&page_size=50')
        if (!active || readRevision !== notificationReadRevision()) return
        const candidates = []
        for (const notice of latest.notifications || []) {
          if (seen.has(notice.id)) continue
          seen.add(notice.id)
          if (notice.related_type === 'blood_request') {
            for (const key of [['my-requests',user.id],['my-responses',user.id],['request-matches'],['home-feed'],['donation-offers']]) queryClient.invalidateQueries({ queryKey: key })
            queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] })
          }
          if (notice.related_type === 'donation_report') {
            queryClient.invalidateQueries({ queryKey: ['donation-chat-contexts'] })
            queryClient.invalidateQueries({ queryKey: ['donation-reports'] })
            queryClient.invalidateQueries({ queryKey: ['confirmed-donation-summary'] })
            queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] })
          }
          if (notice.related_type === 'verification' || notice.type === 'verification.requested') {
            queryClient.invalidateQueries({ queryKey: ['admin-verifications'] })
            queryClient.invalidateQueries({ queryKey: ['admin-verification-detail'] })
            queryClient.invalidateQueries({ queryKey: ['admin-dashboard'] })
          }
          if (initial) continue
          const kind = liveNotificationKind(notice, { baseline, now, viewing: inCurrentView(notice) })
          if (kind) candidates.push({ notice, kind })
        }
        candidates.sort((a,b) => Number(b.kind === 'emergency') - Number(a.kind === 'emergency') || Number(b.notice.id) - Number(a.notice.id))
        for (const { notice, kind } of candidates) {
          const claimed = await claimLiveNotification(user.id, notice.id, kind)
          if (!active || document.hidden || readRevision !== notificationReadRevision()) return
          if (inCurrentView(notice)) continue
          if (!claimed) continue
          const to = notificationDeepLink(notice,user.role)
          notify({ source: 'background', severity: kind === 'emergency' ? 'emergency' : 'info',
            title: kind === 'emergency' ? 'Emergency blood request' : notificationDisplayText(notice,'title'),
            message: 'Open BloodMatch to review this update.', dedupKey: `notification:${user.id}:${notice.id}`,
            action: to ? { to, label: kind === 'emergency' ? 'View request' : notice.type === 'offer.accepted' && notice.related_conversation_id ? 'Open chat' : 'View details' } : undefined })
          break
        }
      } catch {
        baseline = null
      } finally { isFetching = false; if (active) scheduleNext() }
    }
    const scheduleNext = () => { if (timeoutId) clearTimeout(timeoutId); if (active) timeoutId = setTimeout(fetchUnread,5000) }
    const reconnect = () => { baseline = null; if (timeoutId) clearTimeout(timeoutId); fetchUnread() }
    fetchUnread()
    document.addEventListener('visibilitychange',reconnect)
    window.addEventListener('online',reconnect)
    window.addEventListener('focus',reconnect)
    return () => {
      active = false
      if (timeoutId) clearTimeout(timeoutId)
      document.removeEventListener('visibilitychange',reconnect)
      window.removeEventListener('online',reconnect)
      window.removeEventListener('focus',reconnect)
    }
  }, [user?.id, user?.role, notify])

  const isActive = (path) => location.pathname === path
  const isResponsesActive = isActive('/responses') || isActive('/my-responses')
  const isDonationReportsActive = isActive('/donation-reports')
  const isMatchesActive = isActive('/matches') || (/^\/requests\/[^/]+\/matches$/.test(location.pathname) && !isResponsesActive)
  const isPortalActive = location.pathname.startsWith('/officer') ||
    location.pathname.startsWith('/admin') ||
    location.pathname === '/demand-map' ||
    location.pathname === '/analytics'

  useEffect(() => {
    setMobileOpen(false)
    setProfileMenuOpen(false)
  }, [location.pathname])
  useEffect(() => {
    if (!mobileOpen) return undefined
    const onKey = (e) => { if (e.key === 'Escape') setMobileOpen(false) }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [mobileOpen])

  const handleLogout = async () => {
    setProfileMenuOpen(false)
    setLogoutConfirmOpen(false)
    setIsLoggingOut(true)
    try {
      await logout()
    } finally {
      clearCsrf()
      setIsLoggingOut(false)
      setMobileOpen(false)
      navigate('/', { replace: true })
    }
  }

  const handleAvailabilityChange = async () => {
    const next = user.availability === 'available' ? 'unavailable' : 'available'
    setAvailabilityError(null)
    setAvailabilityUpdating(true)
    try {
      await api.post('/api/profile/donor-availability', { availability: next })
      await refresh()
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
      setProfileMenuOpen(false)
      notify({ severity: 'success', message: `Donor availability set to ${next}.` })
    } catch (error) {
      const window = error.details?.availability_window
      const eligibleAt = window?.eligible_again_at_utc
      setAvailabilityError(eligibleAt ? `Donation waiting period ends ${formatNotificationTime(eligibleAt)}.` : (error.message || 'Availability could not be updated.'), { action: { to: '/profile', label: 'View eligibility' } })
      if (window || [403,409].includes(error.status)) setAvailabilityWiggle(Date.now())
    } finally {
      setAvailabilityUpdating(false)
    }
  }

  return (
    <div className={location.pathname === '/chat' ? 'app-shell app-shell--chat' : 'app-shell'}>
      <a href="#main-content" className="skip-link">Skip to main content</a>
      <header className="topbar">
        <div className="topbar-inner">
          <div className="topbar-left">
            <Link to="/" className="brand" aria-label="BloodMatch Home">
              <img src="/favicon-demolay.png?v=2" alt="BloodMatch" className="brand-mark" style={{ width: '28px', height: '28px', borderRadius: '50%' }} />
            </Link>

          </div>

          <nav className="nav nav--desktop" aria-label="Main Navigation">
            <Link to="/" className={`nav-link nav-control ${isActive('/') ? 'active' : ''}`} aria-current={isActive('/') ? 'page' : undefined} title="Home">
              <House size={28} weight={isActive('/') ? 'fill' : 'regular'} />
            </Link>
            {user && (
              <Link to="/matches" className={`nav-link nav-control ${isMatchesActive ? 'active' : ''}`} aria-current={isMatchesActive ? 'page' : undefined} aria-label="Matches" title="Matches">
                <Users size={28} weight={isMatchesActive ? 'fill' : 'regular'} />
              </Link>
            )}
            {user && (
              <Link to="/responses" className={`nav-link nav-control ${isResponsesActive ? 'active' : ''}`} aria-current={isResponsesActive ? 'page' : undefined} aria-label="My Responses" title="My Responses">
                <HandHeart size={28} weight={isResponsesActive ? 'fill' : 'regular'} />
              </Link>
            )}
            {user && (
              <Link to="/donation-reports" className={`nav-link nav-control ${isDonationReportsActive ? 'active' : ''}`} aria-current={isDonationReportsActive ? 'page' : undefined} aria-label="Donation Reports" title="Donation Reports">
                <FileText size={28} weight={isDonationReportsActive ? 'fill' : 'regular'} />
              </Link>
            )}

            {user?.role === 'admin' && (
              <Link
                to="/admin/dashboard"
                className={`nav-link nav-control ${isPortalActive || isActive('/officer') ? 'active' : ''}`}
                aria-current={isPortalActive || isActive('/officer') ? 'page' : undefined}
                title="Admin Portal"
                aria-label="Admin Portal"
              >
                <ShieldCheck size={28} weight={isActive('/officer') || isPortalActive ? 'fill' : 'regular'} />
              </Link>
            )}
          </nav>

          <div className="topbar-right">
            <div className="nav-actions nav-actions--desktop">
              {user ? (
                <>
                  <button
                    type="button"
                    onClick={openCreateRequest}
                    className="nav-control nav-create-plain"
                    title="Create request"
                    aria-label="Create blood request"
                  >
                    <Plus size={28} weight="bold" aria-hidden="true" />
                  </button>
                  <MessengerFlyout variant="desktop" />
                  <NotificationFlyout unread={unread} setUnread={setUnread} variant="desktop" />
                  <div style={{ position: 'relative' }} ref={profileMenuRef}>
                    <button
                      type="button"
                      onClick={() => setProfileMenuOpen(!profileMenuOpen)}
                      aria-expanded={profileMenuOpen}
                      className="nav-avatar-trigger"
                      title="Account menu"
                    >
                      <div className="nav-avatar-status-wrap">
                        <NavbarAvatar src={user.profile_picture_url} alt={`${user.full_name}'s profile picture`} />
                        {user.role === 'member' && user.donor_enrolled && user.donor_eligible === true && (
                          <span className="nav-availability-dot" role="img" aria-label="Available to donate" title="Available to donate" />
                        )}
                        <div style={{
                          position: 'absolute',
                          bottom: -4,
                          right: -4,
                          backgroundColor: 'var(--color-surface)',
                          borderRadius: '50%',
                          padding: '2px',
                          display: 'flex',
                          boxShadow: 'var(--shadow-sm)',
                          border: '1px solid var(--color-border)'
                        }}>
                          <CaretDown size={10} weight="bold" style={{ color: 'var(--color-text)' }} />
                        </div>
                      </div>
                    </button>
                    {profileMenuOpen && (
                      <div className="nav-dropdown">
                        <Link to="/profile" className="dropdown-profile-header dropdown-profile-header-link" onClick={() => setProfileMenuOpen(false)} title="View profile" aria-label="View profile">
                          <NavbarAvatar src={user.profile_picture_url} alt={`${user.full_name}'s profile picture`} className="dropdown-avatar-large" />
                          <div className="dropdown-profile-info">
                            <strong>{user.full_name}</strong>
                          </div>
                        </Link>

                        <hr className="dropdown-divider" />

                        <Link to="/profile" className="dropdown-menu-item" onClick={() => setProfileMenuOpen(false)}>
                          <div className="dropdown-icon-wrapper"><ClipboardText size={20} weight="fill" /></div>
                          <span className="dropdown-item-text">My Requests</span>
                          <CaretRight size={16} className="dropdown-caret" />
                        </Link>


                        {user.role === 'admin' && (
                          <Link to="/admin/dashboard" className="dropdown-menu-item" onClick={() => setProfileMenuOpen(false)}>
                            <div className="dropdown-icon-wrapper"><ShieldCheck size={20} weight="fill" /></div>
                            <span className="dropdown-item-text">Admin Portal</span>
                            <CaretRight size={16} className="dropdown-caret" />
                          </Link>
                        )}

                        {user.role === 'member' && user.verification_status === 'verified' && user.donor_enrolled && (
                          <button type="button" className={availabilityWiggle ? "dropdown-menu-item feedback-wiggle" : "dropdown-menu-item"} key={availabilityWiggle} onAnimationEnd={event => event.currentTarget.classList.remove('feedback-wiggle')} onClick={handleAvailabilityChange} disabled={availabilityUpdating}>
                            <div className="dropdown-icon-wrapper">
                              <Circle size={20} weight="fill" style={{ color: user.availability === 'available' ? 'var(--color-success-green)' : 'var(--color-text-subtle)' }} />
                            </div>
                            <span className="dropdown-item-text">{availabilityUpdating ? 'Updating availability…' : (user.availability === 'available' ? 'Set unavailable' : 'Set available')}</span>
                            <CaretRight size={16} className="dropdown-caret" />
                          </button>
                        )}

                        {user.role === 'member' && (user.verification_status !== 'verified' || !user.donor_enrolled) && (
                          <Link to="/profile" className="dropdown-menu-item" onClick={() => setProfileMenuOpen(false)}>
                            <div className="dropdown-icon-wrapper"><Circle size={20} weight="fill" style={{ color: 'var(--color-text-subtle)' }} /></div>
                            <span className="dropdown-item-text">Complete donor setup</span>
                            <CaretRight size={16} className="dropdown-caret" />
                          </Link>
                        )}



                        <button type="button" className="dropdown-menu-item" onClick={() => { toggleTheme(); setProfileMenuOpen(false); }}>
                          <div className="dropdown-icon-wrapper">
                            {theme === 'light' ? <Moon size={20} weight="fill" /> : <Sun size={20} weight="fill" />}
                          </div>
                          <span className="dropdown-item-text">{theme === 'light' ? 'Dark Mode' : 'Light Mode'}</span>
                          <CaretRight size={16} className="dropdown-caret" />
                        </button>

                        <button type="button" className="dropdown-menu-item" onClick={openDeleteAccount}>
                          <div className="dropdown-icon-wrapper"><User size={20} /></div>
                          <span className="dropdown-item-text">Delete account</span>
                          <CaretRight size={16} className="dropdown-caret" />
                        </button>
                        <button type="button" className="dropdown-menu-item" onClick={() => setLogoutConfirmOpen(true)}>
                          <div className="dropdown-icon-wrapper"><SignOut size={20} weight="fill" /></div>
                          <span className="dropdown-item-text">Log out</span>
                          <CaretRight size={16} className="dropdown-caret" />
                        </button>
                      </div>
                    )}
                  </div>
                </>
              ) : null}
            </div>

            <div className="topbar-utilities">
              {user && (
                <>
                  <button
                    type="button"
                    onClick={openCreateRequest}
                    className="nav-control nav-create-plain nav-icon-btn--mobile"
                    title="Create request"
                    aria-label="Create blood request"
                  >
                    <Plus size={28} weight="bold" aria-hidden="true" />
                  </button>
                  <span className="notif-mobile" title="Chats">
                    <MessengerFlyout variant="mobile" />
                  </span>
                  <span className="notif-mobile" title="Notifications">
                    <NotificationFlyout unread={unread} setUnread={setUnread} variant="desktop" />
                  </span>
                </>
              )}
              <span
                className="hamburger"
                title="Menu"
                onKeyDown={(e) => {
                  if (e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault()
                    setMobileOpen((v) => !v)
                  }
                }}
              >
                <Hamburger
                  toggled={mobileOpen}
                  toggle={setMobileOpen}
                  size={20}
                  rounded
                  duration={0.22}
                  color="currentColor"
                  label={mobileOpen ? 'Close navigation menu' : 'Open navigation menu'}
                />
              </span>
            </div>
          </div>
        </div>

        {mobileOpen && (
          <div id="mobile-panel" className="mobile-panel" role="region" aria-label="Mobile navigation">
            <nav className="mobile-nav" aria-label="Mobile navigation">
              <Link to="/" className={`mobile-link ${isActive('/') ? 'active' : ''}`} aria-current={isActive('/') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Home</Link>
              {user ? (
                <>
                  <Link to="/matches" className={`mobile-link ${isMatchesActive ? 'active' : ''}`} aria-current={isMatchesActive ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Matches</Link>
                  <Link to="/responses" className={`mobile-link ${isResponsesActive ? 'active' : ''}`} aria-current={isResponsesActive ? 'page' : undefined} onClick={() => setMobileOpen(false)}>My Responses</Link>
                  <Link to="/donation-reports" className={`mobile-link ${isDonationReportsActive ? 'active' : ''}`} aria-current={isDonationReportsActive ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Donation Reports</Link>
                  <div className="mobile-section-label">Account</div>

                  <Link to="/profile" className="mobile-link" onClick={() => setMobileOpen(false)}>My Requests</Link>
                  <Link to="/notifications" className={`mobile-link ${isActive('/notifications') ? 'active' : ''}`} aria-label={`Notifications, ${unread} unread`} aria-current={isActive('/notifications') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Notifications {unread > 0 && <span className="nav-badge">{unread}</span>}</Link>
                  {user.role === 'admin' && (
                    <>
                      <div className="mobile-section-label">Administrative</div>
                      <Link to="/admin/dashboard" className={`mobile-link ${isPortalActive ? 'active' : ''}`} aria-current={isPortalActive ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Admin Portal</Link>
                    </>
                  )}

                  <button type="button" className="mobile-link" onClick={openDeleteAccount}>Delete account</button>
                  <div className="mobile-section-label">Session</div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', marginBottom: 'var(--space-2)' }}>
                    <NavbarAvatar src={user.profile_picture_url} alt={`${user.full_name}'s profile picture`} />
                    <div className="user-identity user-identity--mobile" title={user.email} style={{ alignSelf: 'flex-start' }}>
                      <span className="user-name"><strong>{user.full_name}</strong></span>
                      <span className="user-status" aria-label={`Role: ${user.role}`}>({user.role})</span>
                    </div>
                  </div>
                  <button type="button" className="btn btn-secondary" style={{ width: '100%' }} onClick={() => setLogoutConfirmOpen(true)}>Log out</button>
                </>
              ) : (
                <>
                  <div className="mobile-section-label">Access</div>

                  <Link to="/register" className={`mobile-link ${isActive('/register') ? 'active' : ''}`} aria-current={isActive('/register') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Register</Link>
                </>
              )}
            </nav>
          </div>
        )}
      </header>

      <main id="main-content" className={location.pathname === '/chat' ? 'main--chat' : isPortalActive ? 'main--portal' : (location.pathname === '/' && !user) ? 'main--landing' : 'container'}>
        {loading || isLoggingOut ? (
          <div className="card text-center" style={{ padding: 'var(--space-12) var(--space-8)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 'var(--space-4)', minHeight: '40vh', border: 'none', background: 'transparent' }}>
            <CircleNotch className="spinning" size={48} color="var(--color-brand-navy)" />
            <p className="muted" style={{ margin: 0, fontWeight: 500 }}>{isLoggingOut ? 'Logging out…' : 'Loading session…'}</p>
          </div>
        ) : (
          <Routes>
            <Route path="/" element={user ? <HomeFeedPage /> : <LandingPage />} />

            <Route path="/chat" element={<RequireAuth><ChatPage /></RequireAuth>} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/profile" element={<RequireAuth><ProfilePage /></RequireAuth>} />
            <Route path="/profile/:id" element={<RequireAuth><ProfilePage /></RequireAuth>} />

            {/* Nested Admin Portal Routes */}
            <Route
              path="/admin"
              element={
                <RequireAuth roles={['admin']}>
                  <AdminLayout />
                </RequireAuth>
              }
            >
              <Route index element={<Navigate replace to="dashboard" />} />
              <Route path="chat-reports" element={<ChatReportsView />} />
                <Route path="dashboard" element={<DashboardView />} />
              <Route path="verifications" element={<AdminVerificationsView />} />
              <Route path="users" element={<UsersView />} />
              <Route path="confirmations" element={<ConfirmationsView />} />
              <Route path="demand-map" element={<DemandMapView />} />
              <Route path="analytics" element={<AnalyticsView />} />
              <Route path="audit-logs" element={<AuditLogsView />} />
            </Route>

            {/* Legacy bookmarks redirect to the combined staff portal. */}
            <Route path="/officer" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/dashboard" /></RequireAuth>} />
            <Route path="/officer/dashboard" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/dashboard" /></RequireAuth>} />
            <Route path="/officer/verifications" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/verifications" /></RequireAuth>} />
            <Route path="/officer/verification" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/verifications" /></RequireAuth>} />
            <Route path="/officer/confirmations" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/confirmations" /></RequireAuth>} />
            <Route path="/officer/audit-logs" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/audit-logs" /></RequireAuth>} />
            <Route path="/officer/users" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/users" /></RequireAuth>} />
            <Route path="/demand-map" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/demand-map" /></RequireAuth>} />
            <Route path="/analytics" element={<RequireAuth roles={['admin']}><Navigate replace to="/admin/analytics" /></RequireAuth>} />

            {/* Standard Member Routes */}
            <Route path="/requests/new" element={<RequireAuth><CreateRequestEntry /></RequireAuth>} />
            <Route path="/requests/mine" element={<RequireAuth><Navigate to="/profile" replace /></RequireAuth>} />
            <Route path="/requests/:id/matches" element={<RequireAuth><MatchesPage /></RequireAuth>} />
            <Route path="/matches" element={<RequireAuth><MatchesPage /></RequireAuth>} />
            <Route path="/donation-reports" element={<RequireAuth><DonationReportsPage /></RequireAuth>} />
            <Route path="/responses" element={<RequireAuth><MyResponsesPage /></RequireAuth>} />
            <Route path="/my-responses" element={<RequireAuth><Navigate to="/responses" replace /></RequireAuth>} />
            <Route path="/notifications" element={<RequireAuth><NotificationsPage /></RequireAuth>} />

            <Route path="*" element={
              <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
                <h2>Page Not Found</h2>
                <p className="muted">The requested page does not exist or has been moved.</p>
                <Link to="/" className="btn">Return Home</Link>
              </div>
            } />
          </Routes>
        )}
      </main>
      {location.pathname !== '/chat' && <FloatingChatContainer />}
      {user && <DeleteAccount open={deleteAccountOpen} onClose={closeDeleteAccount} />}
      <ConfirmationDialog open={logoutConfirmOpen} title="Log out of BloodMatch?" confirmLabel="Log out" cancelLabel="Stay signed in"
        busy={isLoggingOut} onConfirm={handleLogout} onCancel={() => setLogoutConfirmOpen(false)} />
    </div>
  )
}
