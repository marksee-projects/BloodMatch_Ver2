import { useState, useEffect, useId, useRef } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import {
  ChartBar,
  ChartLineUp,
  CheckCircle,
  House,
  List,
  MapPin,
  Scroll,
  ShieldCheck,
  User,
  X
} from '@phosphor-icons/react'
import { useAuth } from '../../context/AuthContext'
import styles from '../../components/PortalLayout.module.css'

export default function AdminLayout() {
  const { user } = useAuth()
  const location = useLocation()

  const [collapsed, setCollapsed] = useState(() => {
    try {
      return localStorage.getItem('bloodmatch_admin_sidebar_collapsed') === 'true'
    } catch {
      return false
    }
  })
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false)
  const [isMobile, setIsMobile] = useState(() => typeof window !== 'undefined' && window.matchMedia('(max-width: 960px)').matches)
  const menuId = useId()
  const menuButton = useRef(null)
  const sidebarCollapsed = collapsed && !isMobile

  useEffect(() => {
    const media = window.matchMedia('(max-width: 960px)')
    const update = () => { setIsMobile(media.matches); setMobileDrawerOpen(false) }
    update()
    media.addEventListener('change', update)
    return () => media.removeEventListener('change', update)
  }, [])

  const toggleCollapse = () => {
    const next = !collapsed
    setCollapsed(next)
    try {
      localStorage.setItem('bloodmatch_admin_sidebar_collapsed', String(next))
    } catch {
      // ignore storage errors
    }
  }

  // Close mobile drawer and scroll to top on sub-route transition
  useEffect(() => {
    setMobileDrawerOpen(false)
    window.scrollTo({ top: 0, behavior: 'instant' })
  }, [location.pathname])

  const adminNavItems = [
    { label: 'Dashboard', path: '/admin/dashboard', icon: ChartBar },
    { label: 'Account Verifications', path: '/admin/verifications', icon: ShieldCheck },
    { label: 'User Management', path: '/admin/users', icon: User },
    { label: 'Donation Confirmations', path: '/admin/confirmations', icon: CheckCircle },
    { label: 'Demand Map', path: '/admin/demand-map', icon: MapPin },
    { label: 'Analytics', path: '/admin/analytics', icon: ChartLineUp },
    { label: 'Chat Reports', path: '/admin/chat-reports', icon: ShieldCheck },
    { label: 'Audit Logs', path: '/admin/audit-logs', icon: Scroll }
  ]

  const isActive = (path) => {
    if (path === '/admin/dashboard') {
      return location.pathname === '/admin' || location.pathname === '/admin/dashboard'
    }
    return location.pathname.startsWith(path)
  }

  return (
    <div className={`${styles.container} ${sidebarCollapsed ? styles.collapsed : ''}`}>
      {/* Mobile Drawer Bar */}
      <div className={styles.mobileBar}>
        <button
          type="button"
          className="btn btn-secondary btn-sm"
          ref={menuButton}
          aria-expanded={mobileDrawerOpen}
          aria-controls={menuId}
          onClick={() => setMobileDrawerOpen(!mobileDrawerOpen)}
          aria-label={mobileDrawerOpen ? 'Close admin menu' : 'Open admin menu'}
        >
          {mobileDrawerOpen ? <X size={18} /> : <List size={18} />}
          <span style={{ marginLeft: '6px' }}>Admin Portal Menu</span>
        </button>
      </div>

      {/* Persistent Sidebar Navigation */}
      <aside
        className={`${styles.sidebar} ${mobileDrawerOpen ? styles.sidebarMobileOpen : ''}`}
        id={menuId}
        onKeyDown={event => { if (isMobile && event.key === 'Escape') { setMobileDrawerOpen(false); menuButton.current?.focus() } }}
      >
        <div className={styles.sidebarHeader} style={{ borderBottom: 'none', marginBottom: 'var(--space-4)', alignItems: 'center' }}>
          {/* User Profile Section at Top */}
          {!sidebarCollapsed && (
            <Link to="/profile" style={{
              display: 'flex',
              alignItems: 'center',
              gap: '12px',
              overflow: 'hidden',
              transition: 'all 0.2s',
              textDecoration: 'none',
              flex: 1,
              minWidth: 0
            }}>
              {user?.profile_picture_url ? (
                <img
                  src={user.profile_picture_url}
                  alt={user?.full_name}
                  style={{
                    width: '36px',
                    height: '36px',
                    borderRadius: '50%',
                    objectFit: 'cover',
                    flexShrink: 0,
                    backgroundColor: 'var(--color-surface-sunken)'
                  }}
                  onError={(e) => {
                    e.currentTarget.style.display = 'none';
                    if (e.currentTarget.nextElementSibling) {
                      e.currentTarget.nextElementSibling.style.display = 'flex';
                    }
                  }}
                />
              ) : null}
              <div style={{
                width: '36px',
                height: '36px',
                borderRadius: '50%',
                background: 'var(--color-brand-navy)',
                color: 'white',
                display: user?.profile_picture_url ? 'none' : 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 700,
                flexShrink: 0
              }}>
                {user?.full_name?.charAt(0) || 'A'}
              </div>

              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: '0.9rem', color: 'var(--color-text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {user?.full_name}
                </div>
                <div style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  Administrator
                </div>
              </div>
            </Link>
          )}

          <button
            type="button"
            onClick={isMobile ? () => { setMobileDrawerOpen(false); menuButton.current?.focus() } : toggleCollapse}
            title={isMobile ? 'Close admin menu' : collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-label={isMobile ? 'Close admin menu' : collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            style={{
              background: 'transparent',
              border: 'none',
              cursor: 'pointer',
              color: 'var(--color-text)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              minWidth: '44px',
              minHeight: '44px',
              padding: 'var(--space-1)',
              marginLeft: sidebarCollapsed ? '0' : 'auto'
            }}
          >
            {isMobile ? <X size={22} /> : <List size={22} />}
          </button>
        </div>

        <nav className={styles.nav} aria-label="Admin Navigation" style={{ flex: 1 }}>
          <div className={styles.sectionLabel}>
            {!sidebarCollapsed ? 'Administrative Tools' : '•••'}
          </div>

          {adminNavItems.map((item) => {
            const Icon = item.icon
            const active = isActive(item.path)
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`${styles.navLink} ${active ? styles.navLinkActive : ''}`}
                title={sidebarCollapsed ? item.label : undefined}
                aria-current={active ? 'page' : undefined}
              >
                <Icon size={20} weight={active ? 'fill' : 'regular'} className={styles.navIcon} />
                {!sidebarCollapsed && <span className={styles.navLabel}>{item.label}</span>}
              </Link>
            )
          })}

          <div className={styles.sectionLabel} style={{ marginTop: 'var(--space-4)' }}>
            {!sidebarCollapsed ? 'Quick Shortcuts' : '•••'}
          </div>

          <Link
            to="/"
            className={styles.navLink}
            title={sidebarCollapsed ? 'Public Home' : undefined}
          >
            <House size={20} weight="regular" className={styles.navIcon} />
            {!sidebarCollapsed && <span className={styles.navLabel}>Public Home</span>}
          </Link>
        </nav>
      </aside>

      {/* Dynamic Main Viewport via React Router Outlet */}
      <section className={styles.mainContent}>
        <Outlet />
      </section>
    </div>
  )
}
