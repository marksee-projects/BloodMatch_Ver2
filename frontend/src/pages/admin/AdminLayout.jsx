import { useState, useEffect } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import {
  CaretLeft,
  CaretRight,
  ChartBar,
  ChartLineUp,
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
    { label: 'Demand Map', path: '/admin/demand-map', icon: MapPin },
    { label: 'Analytics', path: '/admin/analytics', icon: ChartLineUp },
    { label: 'Audit Logs', path: '/admin/audit-logs', icon: Scroll }
  ]

  const isActive = (path) => {
    if (path === '/admin/dashboard') {
      return location.pathname === '/admin' || location.pathname === '/admin/dashboard'
    }
    return location.pathname.startsWith(path)
  }

  return (
    <div className={`${styles.container} ${collapsed ? styles.collapsed : ''}`} style={{ paddingBlock: 0, paddingLeft: 0, minHeight: '100vh', gap: 0 }}>
      {/* Mobile Drawer Bar */}
      <div className={styles.mobileBar} style={{ padding: 'var(--space-4)' }}>
        <button
          type="button"
          className="btn btn-secondary btn-sm"
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
        style={{
          borderRadius: 0,
          borderTop: 'none',
          borderBottom: 'none',
          borderLeft: 'none',
          top: '64px',
          minHeight: 'calc(100vh - 64px)',
          height: 'calc(100vh - 64px)',
          marginBottom: 0,
          paddingTop: 'var(--space-5)',
          display: 'flex',
          flexDirection: 'column',
          position: 'sticky',
          alignSelf: 'start',
          overflowY: 'auto'
        }}
      >
        <div className={styles.sidebarHeader} style={{ borderBottom: 'none', marginBottom: 'var(--space-4)', alignItems: 'center' }}>
          {/* User Profile Section at Top */}
          {!collapsed && (
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
            onClick={toggleCollapse}
            title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            style={{
              background: 'transparent',
              border: 'none',
              cursor: 'pointer',
              color: 'var(--color-text)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              padding: '4px',
              marginLeft: collapsed ? '0' : 'auto'
            }}
          >
            <List size={22} />
          </button>
        </div>

        <nav className={styles.nav} aria-label="Admin Navigation" style={{ flex: 1 }}>
          <div className={styles.sectionLabel}>
            {!collapsed ? 'Administrative Tools' : '•••'}
          </div>

          {adminNavItems.map((item) => {
            const Icon = item.icon
            const active = isActive(item.path)
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`${styles.navLink} ${active ? styles.navLinkActive : ''}`}
                title={collapsed ? item.label : undefined}
                aria-current={active ? 'page' : undefined}
              >
                <Icon size={20} weight={active ? 'fill' : 'regular'} className={styles.navIcon} />
                {!collapsed && <span className={styles.navLabel}>{item.label}</span>}
              </Link>
            )
          })}

          <div className={styles.sectionLabel} style={{ marginTop: 'var(--space-4)' }}>
            {!collapsed ? 'Quick Shortcuts' : '•••'}
          </div>

          <Link
            to="/"
            className={styles.navLink}
            title={collapsed ? 'Public Home' : undefined}
          >
            <House size={20} weight="regular" className={styles.navIcon} />
            {!collapsed && <span className={styles.navLabel}>Public Home</span>}
          </Link>
        </nav>
      </aside>

      {/* Dynamic Main Viewport via React Router Outlet */}
      <section className={styles.mainContent} style={{ padding: 'var(--space-6)' }}>
        <Outlet />
      </section>
    </div>
  )
}
