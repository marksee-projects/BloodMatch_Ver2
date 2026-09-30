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

  const currentNavItem = adminNavItems.find((item) => isActive(item.path)) || adminNavItems[0]

  return (
    <div className={`${styles.container} ${collapsed ? styles.collapsed : ''}`}>
      {/* Mobile Drawer Bar */}
      <div className={styles.mobileBar}>
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
      <aside className={`${styles.sidebar} ${mobileDrawerOpen ? styles.sidebarMobileOpen : ''}`}>
        <div className={styles.sidebarHeader}>
          {!collapsed && (
            <div className={styles.titleBlock}>
              <span className={styles.badge}>System Administrator</span>
              <h3 className={styles.title}>Admin Portal</h3>
            </div>
          )}

          <button
            type="button"
            className={styles.collapseBtn}
            onClick={toggleCollapse}
            title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          >
            {collapsed ? <CaretRight size={16} /> : <CaretLeft size={16} />}
          </button>
        </div>

        <nav className={styles.nav} aria-label="Admin Navigation">
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
            to="/profile"
            className={`${styles.navLink} ${location.pathname === '/profile' ? styles.navLinkActive : ''}`}
            title={collapsed ? 'My Profile' : undefined}
          >
            <User size={20} weight="regular" className={styles.navIcon} />
            {!collapsed && <span className={styles.navLabel}>My Profile</span>}
          </Link>

          <Link
            to="/"
            className={styles.navLink}
            title={collapsed ? 'Public Home' : undefined}
          >
            <House size={20} weight="regular" className={styles.navIcon} />
            {!collapsed && <span className={styles.navLabel}>Public Home</span>}
          </Link>
        </nav>

        {!collapsed && (
          <div className={styles.sidebarFooter}>
            <div className={styles.statusDotContainer}>
              <span className={styles.statusDot} aria-hidden="true" />
              <span style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-muted)' }}>
                System Secure · All Chapters Scope
              </span>
            </div>
          </div>
        )}
      </aside>

      {/* Dynamic Main Viewport via React Router Outlet */}
      <section className={styles.mainContent}>
        {/* Contextual Header / Breadcrumbs */}
        <div style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: 'var(--space-4)',
          paddingBottom: 'var(--space-2)',
          borderBottom: '1px solid var(--color-border-subtle)',
          fontSize: 'var(--text-xs)',
          color: 'var(--color-text-muted)',
          fontFamily: 'var(--font-mono)'
        }}>
          <div>
            <Link to="/admin/dashboard" style={{ color: 'var(--color-text-muted)', textDecoration: 'none' }}>Admin Portal</Link>
            <span style={{ margin: '0 var(--space-2)' }}>/</span>
            <span style={{ color: 'var(--color-text)', fontWeight: 600 }}>{currentNavItem.label}</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
            <span className="badge badge-open" style={{ fontSize: '0.625rem' }}>Global Admin</span>
          </div>
        </div>

        <Outlet />
      </section>
    </div>
  )
}
