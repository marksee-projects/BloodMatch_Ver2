import { useEffect, useState, useRef } from 'react'
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { CaretDown, CaretRight, Circle, Moon, Plus, Sun, User, House, ClipboardText, ShieldCheck, SignOut, MagnifyingGlass, CircleNotch } from '@phosphor-icons/react'
import { Divide as Hamburger } from 'hamburger-react'
import { useAuth } from './context/AuthContext'
import { useTheme } from './context/ThemeContext'
import { clearCsrf } from './services/apiClient'
import RegisterPage from './pages/RegisterPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import ProfilePage from './pages/ProfilePage'
import RequestsPage from './pages/RequestsPage'
import RequestFormPage from './pages/RequestFormPage'
import MatchesPage from './pages/MatchesPage'
import NotificationFlyout from './components/NotificationFlyout'
import Footer from './components/Footer'
import NotificationsPage from './pages/NotificationsPage'
import PortalLayout from './components/PortalLayout'
import AdminLayout from './pages/admin/AdminLayout'
import DashboardView from './pages/admin/views/DashboardView'
import DemandMapView from './pages/admin/views/DemandMapView'
import AnalyticsView from './pages/admin/views/AnalyticsView'
import AuditLogsView from './pages/admin/views/AuditLogsView'
import OfficerLayout from './pages/officer/OfficerLayout'
import OfficerDashboardView from './pages/officer/views/DashboardView'
import OfficerVerificationView from './pages/officer/views/VerificationView'
import OfficerConfirmationsView from './pages/officer/views/ConfirmationsView'
import OfficerAuditLogsView from './pages/officer/views/AuditLogsView'
import DemandMapWidget from './components/DemandMapWidget'
import { api } from './services/apiClient'
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
  if (roles && !roles.includes(user.role)) {
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
        <User size={16} weight="regular" aria-hidden="true" />
      </span>
    )
  }
  return <img className={`avatar ${className || ''}`} src={src} alt={alt} onError={() => setFailed(true)} />
}

export default function App() {
  const { theme, toggleTheme } = useTheme()
  const { user, loading, logout, refresh } = useAuth()
  const [unread, setUnread] = useState(0)
  const location = useLocation()
  const navigate = useNavigate()
  const [mobileOpen, setMobileOpen] = useState(false)
  const [profileMenuOpen, setProfileMenuOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const profileMenuRef = useRef(null)

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

    const fetchUnread = async () => {
      if (!active) return
      if (document.visibilityState !== 'visible') {
        scheduleNext()
        return
      }
      if (isFetching) return
      isFetching = true
      try {
        const d = await api.get('/api/notifications/unread-count')
        if (active && typeof d?.unread_count === 'number') {
          setUnread(d.unread_count)
        }
      } catch (err) {
        // ignore
      } finally {
        isFetching = false
        if (active) scheduleNext()
      }
    }

    const scheduleNext = () => {
      if (timeoutId) clearTimeout(timeoutId)
      if (active) timeoutId = setTimeout(fetchUnread, 45000)
    }

    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        if (timeoutId) clearTimeout(timeoutId)
        fetchUnread()
      }
    }

    fetchUnread()
    document.addEventListener('visibilitychange', handleVisibility)

    return () => {
      active = false
      if (timeoutId) clearTimeout(timeoutId)
      document.removeEventListener('visibilitychange', handleVisibility)
    }
  }, [user])

  const isActive = (path) => location.pathname === path
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
    if (!window.confirm("Are you sure you want to log out?")) return
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

  return (
    <div className="app-shell">
      <a href="#main-content" className="skip-link">Skip to main content</a>
      <header className="topbar">
        <div className="topbar-inner">
          <div className="topbar-left">
            <Link to="/" className="brand" aria-label="BloodMatch Home">
              <img src="/favicon-demolay.png?v=2" alt="BloodMatch" className="brand-mark" style={{ width: '28px', height: '28px', borderRadius: '50%' }} />
            </Link>

            {user && (
              <div className="nav-search-container">
                <MagnifyingGlass size={18} className="nav-search-icon" weight="bold" />
                <input type="text" placeholder="Search BloodMatch" className="nav-search-input" aria-label="Search BloodMatch" />
              </div>
            )}
          </div>

          <nav className="nav nav--desktop" aria-label="Main Navigation">
            <Link to="/" className={`nav-link ${isActive('/') ? 'active' : ''}`} aria-current={isActive('/') ? 'page' : undefined} title="Home">
              <House size={24} weight={isActive('/') ? 'fill' : 'regular'} />
            </Link>
            {user && (
              <Link to="/profile" className={`nav-link ${isActive('/profile') ? 'active' : ''}`} aria-current={isActive('/profile') ? 'page' : undefined} title="Profile">
                <User size={24} weight={isActive('/profile') ? 'fill' : 'regular'} />
              </Link>
            )}
          </nav>

          <div className="topbar-right">
            <div className="nav-actions nav-actions--desktop">
              {user ? (
                <>
                  <Link
                    to="/requests/mine"
                    className="nav-icon-btn nav-icon-btn--accent"
                    title="My Requests"
                    aria-label="Manage blood requests"
                  >
                    <Plus size={24} weight="bold" />
                  </Link>
                  <NotificationFlyout unread={unread} setUnread={setUnread} variant="desktop" />
                  <div style={{ position: 'relative' }} ref={profileMenuRef}>
                    <button 
                      type="button"
                      onClick={() => setProfileMenuOpen(!profileMenuOpen)}
                      aria-expanded={profileMenuOpen}
                      className="nav-avatar-trigger"
                      title="Account menu"
                    >
                      <div style={{ position: 'relative' }}>
                        <NavbarAvatar src={user.profile_picture_url} alt={`${user.full_name}'s profile picture`} />
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
                        <div className="dropdown-profile-header">
                          <NavbarAvatar src={user.profile_picture_url} alt={`${user.full_name}'s profile picture`} className="dropdown-avatar-large" />
                          <div className="dropdown-profile-info">
                            <strong>{user.full_name}</strong>
                            <Link to="/profile" className="btn btn-sm" style={{ padding: '4px 12px', fontSize: 'var(--text-xs)', marginTop: '4px' }} onClick={() => setProfileMenuOpen(false)}>See your profile</Link>
                          </div>
                        </div>
                        
                        <hr className="dropdown-divider" />
                        
                        <Link to="/requests/mine" className="dropdown-menu-item" onClick={() => setProfileMenuOpen(false)}>
                          <div className="dropdown-icon-wrapper"><ClipboardText size={20} weight="fill" /></div>
                          <span className="dropdown-item-text">My Requests</span>
                          <CaretRight size={16} className="dropdown-caret" />
                        </Link>
                        
                        {user.role === 'officer' && (
                          <Link to="/officer/dashboard" className="dropdown-menu-item" onClick={() => setProfileMenuOpen(false)}>
                            <div className="dropdown-icon-wrapper"><ShieldCheck size={20} weight="fill" /></div>
                            <span className="dropdown-item-text">Officer Portal</span>
                            <CaretRight size={16} className="dropdown-caret" />
                          </Link>
                        )}
                        
                        {user.role === 'admin' && (
                          <Link to="/admin/dashboard" className="dropdown-menu-item" onClick={() => setProfileMenuOpen(false)}>
                            <div className="dropdown-icon-wrapper"><ShieldCheck size={20} weight="fill" /></div>
                            <span className="dropdown-item-text">Admin Portal</span>
                            <CaretRight size={16} className="dropdown-caret" />
                          </Link>
                        )}
                        
                        {user.role === 'member' && (
                          <button type="button" className="dropdown-menu-item" onClick={() => {
                            const next = user.availability === 'available' ? 'unavailable' : 'available'
                            api.post('/api/profile/donor-availability', { availability: next }).then(() => refresh()).catch(() => {})
                            setProfileMenuOpen(false)
                          }}>
                            <div className="dropdown-icon-wrapper">
                              <Circle size={20} weight="fill" style={{ color: user.availability === 'available' ? 'var(--color-success-green)' : 'var(--color-text-subtle)' }} />
                            </div>
                            <span className="dropdown-item-text">{user.availability === 'available' ? 'Available to donate' : 'Unavailable to donate'}</span>
                            <CaretRight size={16} className="dropdown-caret" />
                          </button>
                        )}
                        
                        <button type="button" className="dropdown-menu-item" onClick={() => { toggleTheme(); setProfileMenuOpen(false); }}>
                          <div className="dropdown-icon-wrapper">
                            {theme === 'light' ? <Moon size={20} weight="fill" /> : <Sun size={20} weight="fill" />}
                          </div>
                          <span className="dropdown-item-text">{theme === 'light' ? 'Dark Mode' : 'Light Mode'}</span>
                          <CaretRight size={16} className="dropdown-caret" />
                        </button>
                        
                        <button type="button" className="dropdown-menu-item" onClick={handleLogout}>
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
                  <Link
                    to="/requests/mine"
                    className="nav-icon-btn nav-icon-btn--accent nav-icon-btn--mobile"
                    title="My Requests"
                    aria-label="Manage blood requests"
                  >
                    <Plus size={24} weight="bold" />
                  </Link>
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
                  <div className="mobile-section-label">Account</div>
                  <Link to="/profile" className={`mobile-link ${isActive('/profile') ? 'active' : ''}`} aria-current={isActive('/profile') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Profile</Link>
                  <Link to="/requests/mine" className={`mobile-link ${isActive('/requests/mine') ? 'active' : ''}`} aria-current={isActive('/requests/mine') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>My Requests</Link>
                  <Link to="/notifications" className={`mobile-link ${isActive('/notifications') ? 'active' : ''}`} aria-label={`Notifications, ${unread} unread`} aria-current={isActive('/notifications') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Notifications {unread > 0 && <span className="nav-badge">{unread}</span>}</Link>
                  {user.role === 'officer' && (
                    <>
                      <div className="mobile-section-label">Administrative</div>
                      <Link to="/officer/dashboard" className={`mobile-link ${isPortalActive ? 'active' : ''}`} aria-current={isPortalActive ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Officer Portal</Link>
                    </>
                  )}
                  {user.role === 'admin' && (
                    <>
                      <div className="mobile-section-label">Administrative</div>
                      <Link to="/admin/dashboard" className={`mobile-link ${isPortalActive ? 'active' : ''}`} aria-current={isPortalActive ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Admin Portal</Link>
                    </>
                  )}

                  <div className="mobile-section-label">Session</div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', marginBottom: 'var(--space-2)' }}>
                    <NavbarAvatar src={user.profile_picture_url} alt={`${user.full_name}'s profile picture`} />
                    <div className="user-identity user-identity--mobile" title={user.email} style={{ alignSelf: 'flex-start' }}>
                      <span className="user-name"><strong>{user.full_name}</strong></span>
                      <span className="user-status" aria-label={`Role: ${user.role}`}>({user.role})</span>
                    </div>
                  </div>
                  <button type="button" className="btn btn-secondary" style={{ width: '100%' }} onClick={handleLogout}>Log out</button>
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

      <main id="main-content" className={isPortalActive ? 'main--portal' : (location.pathname === '/' && !user) ? 'main--landing' : 'container'}>
        {loading || isLoggingOut ? (
          <div className="card text-center" style={{ padding: 'var(--space-12) var(--space-8)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 'var(--space-4)', minHeight: '40vh', border: 'none', background: 'transparent' }}>
            <CircleNotch className="spinning" size={48} color="var(--color-brand-navy)" />
            <p className="muted" style={{ margin: 0, fontWeight: 500 }}>{isLoggingOut ? 'Logging out…' : 'Loading session…'}</p>
          </div>
        ) : (
          <Routes>
            <Route path="/" element={user ? <HomeFeedPage /> : <LandingPage />} />

            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />
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
              <Route path="dashboard" element={<DashboardView />} />
              <Route path="demand-map" element={<DemandMapView />} />
              <Route path="analytics" element={<AnalyticsView />} />
              <Route path="audit-logs" element={<AuditLogsView />} />
            </Route>

            {/* Nested Officer Portal Routes */}
            <Route
              path="/officer"
              element={
                <RequireAuth roles={['officer']}>
                  <OfficerLayout />
                </RequireAuth>
              }
            >
              <Route index element={<Navigate replace to="dashboard" />} />
              <Route path="dashboard" element={<OfficerDashboardView />} />
              <Route path="verifications" element={<OfficerVerificationView />} />
              <Route path="verification" element={<Navigate replace to="verifications" />} />
              <Route path="confirmations" element={<OfficerConfirmationsView />} />
              <Route path="audit-logs" element={<OfficerAuditLogsView />} />
            </Route>

            {/* Standalone Demand Map & Analytics Routes (Officers use PortalLayout, Admins redirect to /admin/*) */}
            <Route
              path="/demand-map"
              element={
                <RequireAuth roles={['officer', 'admin']}>
                  {user?.role === 'admin' ? (
                    <Navigate replace to="/admin/demand-map" />
                  ) : (
                    <PortalLayout><DemandMapView /></PortalLayout>
                  )}
                </RequireAuth>
              }
            />
            <Route
              path="/analytics"
              element={
                <RequireAuth roles={['officer', 'admin']}>
                  {user?.role === 'admin' ? (
                    <Navigate replace to="/admin/analytics" />
                  ) : (
                    <PortalLayout><AnalyticsView /></PortalLayout>
                  )}
                </RequireAuth>
              }
            />

            {/* Standard Member Routes */}
            <Route path="/requests/mine" element={<RequireAuth><RequestsPage /></RequireAuth>} />
            <Route path="/requests/:id/matches" element={<RequireAuth><MatchesPage /></RequireAuth>} />
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
    </div>
  )
}
