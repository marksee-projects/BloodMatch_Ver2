import { useEffect, useState } from 'react'
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { List, Moon, Sun, X } from '@phosphor-icons/react'
import { useAuth } from './context/AuthContext'
import { useTheme } from './context/ThemeContext'
import { clearCsrf } from './services/apiClient'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import ForgotPasswordPage from './pages/ForgotPasswordPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import ProfilePage from './pages/ProfilePage'
import OfficerVerificationPage from './pages/OfficerVerificationPage'
import OfficerConfirmationsPage from './pages/OfficerConfirmationsPage'
import RequestsPage from './pages/RequestsPage'
import RequestFormPage from './pages/RequestFormPage'
import MatchesPage from './pages/MatchesPage'
import NotificationsPage from './pages/NotificationsPage'
import AdminAuditLogsPage from './pages/AdminAuditLogsPage'
import OfficerAuditLogsPage from './pages/OfficerAuditLogsPage'
import DemandMapPage from './pages/DemandMapPage'
import OfficerDashboardPage from './pages/OfficerDashboardPage'
import AdminDashboardPage from './pages/AdminDashboardPage'
import AnalyticsPage from './pages/AnalyticsPage'
import PortalLayout from './components/PortalLayout'
import DemandMapWidget from './components/DemandMapWidget'
import Hero from './components/Hero'
import { api } from './services/apiClient'

function HomePage() {
  const { user } = useAuth()
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-8)' }}>
      <Hero />

      {/* Embedded Real-time Regional Demand Map for immediate visibility */}
      {user && (
        <section>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--space-3)' }}>
            <div>
              <div className="section-label" style={{ marginBottom: '2px' }}>Live Regional Demand</div>
              <h2 style={{ fontSize: 'var(--text-h2)', margin: 0 }}>Active Demand Overview</h2>
            </div>
            {(user.role === 'officer' || user.role === 'admin') && (
              <Link to="/demand-map" className="btn btn-secondary btn-sm">
                Open Full Demand Map
              </Link>
            )}
          </div>
          <DemandMapWidget compact={false} showFilters={true} />
        </section>
      )}

      {/* Pillars: tighter, typographic */}
      <div>
        <div className="section-label">How matching works</div>
        <section className="grid-3">
          <div className="card card--halftone">
            <div className="kicker" style={{ marginBottom: 'var(--space-2)' }}><span className="font-pixel">01</span>: Compatibility</div>
            <h3 style={{ marginBottom: 'var(--space-2)' }}>Red-cell first</h3>
            <p className="muted" style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6, margin: 0 }}>
              Only ABO + Rh compatible donors are considered. Proximity refines the eligible pool, it never overrides compatibility.
            </p>
          </div>
          <div className="card card--halftone">
            <div className="kicker" style={{ marginBottom: 'var(--space-2)' }}><span className="font-pixel">02</span>: Verification</div>
            <h3 style={{ marginBottom: 'var(--space-2)' }}>Officer review</h3>
            <p className="muted" style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6, margin: 0 }}>
              Donor cards and IDs are checked by chapter officers. Every confirmed donation is logged in a tamper-evident trail.
            </p>
          </div>
          <div className="card card--halftone">
            <div className="kicker" style={{ marginBottom: 'var(--space-2)' }}><span className="font-pixel">03</span>: Safety</div>
            <h3 style={{ marginBottom: 'var(--space-2)' }}>Protected intervals</h3>
            <p className="muted" style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6, margin: 0 }}>
              Post-donation 42-hour standby and 90-day cooldown are enforced automatically; availability reflects them.
            </p>
          </div>
        </section>
      </div>

      {/* Medical disclaimer: quieter */}
      <section className="medical-disclaimer" role="note" aria-label="Medical Disclaimer">
        <div aria-hidden="true" style={{ fontFamily: 'var(--font-mono)', fontSize: '0.7rem', letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--color-text-subtle)', paddingTop: '2px' }}>Note</div>
        <div>
          <strong>Clinical confirmation required.</strong> Suggestions are advisory and do not replace crossmatching, infectious screening or physician review at the facility.
        </div>
      </section>
    </div>
  )
}

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
    return <Navigate to="/login" replace />
  }
  if (roles && !roles.includes(user.role)) {
    return <Navigate to="/" replace />
  }
  return children
}

export default function App() {
  const { theme, toggleTheme } = useTheme()
  const { user, loading, logout } = useAuth()
  const [unread, setUnread] = useState(0)
  const location = useLocation()
  const navigate = useNavigate()
  const [mobileOpen, setMobileOpen] = useState(false)

  useEffect(() => {
    if (!user) {
      setUnread(0)
      return undefined
    }
    let active = true
    const tick = () =>
      api
        .get('/api/notifications/unread-count')
        .then((d) => {
          if (active) setUnread(d.unread_count)
        })
        .catch(() => {})
    tick()
    const t = setInterval(tick, 30000)
    return () => {
      active = false
      clearInterval(t)
    }
  }, [user])

  const isActive = (path) => location.pathname === path
  const isPortalActive = location.pathname.startsWith('/officer') ||
    location.pathname.startsWith('/admin') ||
    location.pathname === '/demand-map' ||
    location.pathname === '/analytics'

  useEffect(() => { setMobileOpen(false) }, [location.pathname])
  useEffect(() => {
    if (!mobileOpen) return undefined
    const onKey = (e) => { if (e.key === 'Escape') setMobileOpen(false) }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [mobileOpen])

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      clearCsrf()
      setMobileOpen(false)
      navigate('/', { replace: true })
    }
  }

  return (
    <>
      <a href="#main-content" className="skip-link">Skip to main content</a>
      <header className="topbar">
        <div className="topbar-inner">
          <Link to="/" className="brand" aria-label="BloodMatch Home">
            <span>BloodMatch</span>
            <span className="brand-badge">Bataan</span>
          </Link>

          <nav className="nav nav--desktop" aria-label="Main Navigation">
            <Link to="/" className={`nav-link ${isActive('/') ? 'active' : ''}`} aria-current={isActive('/') ? 'page' : undefined}>Home</Link>
            {user ? (
              <>
                <Link to="/profile" className={`nav-link ${isActive('/profile') ? 'active' : ''}`} aria-current={isActive('/profile') ? 'page' : undefined}>Profile</Link>
                <Link to="/requests/mine" className={`nav-link ${isActive('/requests/mine') ? 'active' : ''}`} aria-current={isActive('/requests/mine') ? 'page' : undefined}>My Requests</Link>

                {/* Decluttered Portal Navigation: Clean link to dedicated portal */}
                {user.role === 'officer' && (
                  <Link
                    to="/officer/dashboard"
                    className={`nav-link ${isPortalActive ? 'active' : ''}`}
                    aria-current={isPortalActive ? 'page' : undefined}
                  >
                    Officer Portal
                  </Link>
                )}
                {user.role === 'admin' && (
                  <Link
                    to="/admin/dashboard"
                    className={`nav-link ${isPortalActive ? 'active' : ''}`}
                    aria-current={isPortalActive ? 'page' : undefined}
                  >
                    Admin Portal
                  </Link>
                )}

                <Link to="/notifications" className={`nav-link ${isActive('/notifications') ? 'active' : ''}`} aria-label={`Notifications, ${unread} unread`} aria-current={isActive('/notifications') ? 'page' : undefined}>
                  Notifications {unread > 0 && <span className="nav-badge">{unread}</span>}
                </Link>
              </>
            ) : null}
          </nav>

          <div className="nav-actions nav-actions--desktop">
            {user ? (
              <>
                <span className="user-tag" title={user.email}><strong>{user.full_name}</strong> <span>({user.role})</span></span>
                <button type="button" className="btn btn-secondary btn-sm" onClick={handleLogout}>Log out</button>
              </>
            ) : (
              <>
                <Link to="/login" className={`nav-link ${isActive('/login') ? 'active' : ''}`} aria-current={isActive('/login') ? 'page' : undefined}>Log in</Link>
                <Link to="/register" className={`btn btn-sm ${isActive('/register') ? 'active' : ''}`}>Register</Link>
              </>
            )}
          </div>

          <div className="topbar-utilities">
            <button type="button" className="theme-toggle" onClick={toggleTheme} aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`} title={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}>
              {theme === 'light' ? <Moon size={16} weight="regular" aria-hidden="true" /> : <Sun size={16} weight="regular" aria-hidden="true" />}
            </button>
            <button type="button" className="hamburger" aria-label={mobileOpen ? 'Close navigation menu' : 'Open navigation menu'} aria-expanded={mobileOpen} aria-controls="mobile-panel" onClick={() => setMobileOpen(!mobileOpen)}>
              {mobileOpen ? <X size={18} weight="regular" aria-hidden="true" /> : <List size={18} weight="regular" aria-hidden="true" />}
            </button>
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
                  <div className="user-tag" title={user.email} style={{ alignSelf: 'flex-start', marginBottom: 'var(--space-2)' }}><strong>{user.full_name}</strong> <span>({user.role})</span></div>
                  <button type="button" className="btn btn-secondary" style={{ width: '100%' }} onClick={handleLogout}>Log out</button>
                </>
              ) : (
                <>
                  <div className="mobile-section-label">Access</div>
                  <Link to="/login" className={`mobile-link ${isActive('/login') ? 'active' : ''}`} aria-current={isActive('/login') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Log in</Link>
                  <Link to="/register" className={`mobile-link ${isActive('/register') ? 'active' : ''}`} aria-current={isActive('/register') ? 'page' : undefined} onClick={() => setMobileOpen(false)}>Register</Link>
                </>
              )}
            </nav>
          </div>
        )}
      </header>

      <main id="main-content" className={isPortalActive ? 'main--portal' : 'container'}>
        {loading ? (
          <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
            <p className="muted">Loading session…</p>
          </div>
        ) : (
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />
            <Route path="/profile" element={<RequireAuth><ProfilePage /></RequireAuth>} />

            {/* Officer & Admin Portal Routes Wrapped in PortalLayout */}
            <Route path="/officer/dashboard" element={<RequireAuth roles={['officer']}><PortalLayout><OfficerDashboardPage /></PortalLayout></RequireAuth>} />
            <Route path="/admin/dashboard" element={<RequireAuth roles={['admin']}><PortalLayout><AdminDashboardPage /></PortalLayout></RequireAuth>} />
            <Route path="/demand-map" element={<RequireAuth roles={['officer', 'admin']}><PortalLayout><DemandMapPage /></PortalLayout></RequireAuth>} />
            <Route path="/analytics" element={<RequireAuth roles={['officer', 'admin']}><PortalLayout><AnalyticsPage /></PortalLayout></RequireAuth>} />
            <Route path="/officer/verifications" element={<RequireAuth roles={['officer']}><PortalLayout><OfficerVerificationPage /></PortalLayout></RequireAuth>} />
            <Route path="/officer/confirmations" element={<RequireAuth roles={['officer']}><PortalLayout><OfficerConfirmationsPage /></PortalLayout></RequireAuth>} />
            <Route path="/admin/audit-logs" element={<RequireAuth roles={['admin']}><PortalLayout><AdminAuditLogsPage /></PortalLayout></RequireAuth>} />
            <Route path="/officer/audit-logs" element={<RequireAuth roles={['officer']}><PortalLayout><OfficerAuditLogsPage /></PortalLayout></RequireAuth>} />

            {/* Standard Member Routes */}
            <Route path="/requests/mine" element={<RequireAuth><RequestsPage /></RequireAuth>} />
            <Route path="/requests/new" element={<RequireAuth><RequestFormPage /></RequireAuth>} />
            <Route path="/requests/:id/edit" element={<RequireAuth><RequestFormPage /></RequireAuth>} />
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
    </>
  )
}
