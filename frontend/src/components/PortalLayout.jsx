import { useState, useEffect } from 'react'
import { Link, useLocation } from 'react-router-dom'
import {
  ChartBar,
  ChartLineUp,
  CheckSquareOffset,
  CaretLeft,
  CaretRight,
  House,
  List,
  MapPin,
  Scroll,
  ShieldCheck,
  User,
  X
} from '@phosphor-icons/react'
import { useAuth } from '../context/AuthContext'

export default function PortalLayout({ children }) {
  const { user } = useAuth()
  const location = useLocation()

  const [collapsed, setCollapsed] = useState(() => {
    try {
      return localStorage.getItem('bloodmatch_portal_sidebar_collapsed') === 'true'
    } catch {
      return false
    }
  })
  const [mobileDrawerOpen, setMobileDrawerOpen] = useState(false)

  const toggleCollapse = () => {
    const next = !collapsed
    setCollapsed(next)
    try {
      localStorage.setItem('bloodmatch_portal_sidebar_collapsed', String(next))
    } catch {
      // ignore storage errors
    }
  }

  // Close mobile drawer on route change
  useEffect(() => {
    setMobileDrawerOpen(false)
  }, [location.pathname])

  const isActive = (path) => location.pathname === path

  const officerNavItems = [
    { label: 'Dashboard', path: '/officer/dashboard', icon: ChartBar },
    { label: 'Demand Map', path: '/demand-map', icon: MapPin },
    { label: 'Analytics', path: '/analytics', icon: ChartLineUp },
    { label: 'Verifications', path: '/officer/verifications', icon: ShieldCheck },
    { label: 'Confirmations', path: '/officer/confirmations', icon: CheckSquareOffset },
    { label: 'Audit Logs', path: '/officer/audit-logs', icon: Scroll }
  ]

  const adminNavItems = [
    { label: 'Dashboard', path: '/admin/dashboard', icon: ChartBar },
    { label: 'Demand Map', path: '/demand-map', icon: MapPin },
    { label: 'Analytics', path: '/analytics', icon: ChartLineUp },
    { label: 'Audit Logs', path: '/admin/audit-logs', icon: Scroll }
  ]

  const navItems = user?.role === 'admin' ? adminNavItems : officerNavItems
  const portalTitle = user?.role === 'admin' ? 'Admin Portal' : 'Officer Portal'

  return (
    <div className={`portal-container ${collapsed ? 'portal--collapsed' : ''}`}>
      {/* Mobile Drawer Toggle */}
      <div className="portal-mobile-bar">
        <button
          type="button"
          className="btn btn-secondary btn-sm"
          onClick={() => setMobileDrawerOpen(!mobileDrawerOpen)}
          aria-label={mobileDrawerOpen ? 'Close sidebar menu' : 'Open sidebar menu'}
        >
          {mobileDrawerOpen ? <X size={18} /> : <List size={18} />}
          <span style={{ marginLeft: '6px' }}>{portalTitle} Menu</span>
        </button>
      </div>

      {/* Sidebar Navigation */}
      <aside className={`portal-sidebar ${mobileDrawerOpen ? 'portal-sidebar--mobile-open' : ''}`}>
        <div className="portal-sidebar-header">
          {!collapsed && (
            <div className="portal-sidebar-title-block">
              <span className="portal-badge">
                {user?.role === 'admin' ? 'System Administrator' : 'Chapter Officer'}
              </span>
              <h3 className="portal-title">{portalTitle}</h3>
            </div>
          )}

          <button
            type="button"
            className="portal-collapse-btn"
            onClick={toggleCollapse}
            title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          >
            {collapsed ? <CaretRight size={16} /> : <CaretLeft size={16} />}
          </button>
        </div>

        <nav className="portal-nav" aria-label="Portal Secondary Navigation">
          <div className="portal-nav-section-label">
            {!collapsed ? 'Administrative Tools' : '•••'}
          </div>

          {navItems.map((item) => {
            const Icon = item.icon
            const active = isActive(item.path)
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`portal-nav-link ${active ? 'active' : ''}`}
                title={collapsed ? item.label : undefined}
                aria-current={active ? 'page' : undefined}
              >
                <Icon size={20} weight={active ? 'fill' : 'regular'} className="portal-nav-icon" />
                {!collapsed && <span className="portal-nav-label">{item.label}</span>}
              </Link>
            )
          })}

          <div className="portal-nav-section-label" style={{ marginTop: 'var(--space-4)' }}>
            {!collapsed ? 'Quick Shortcuts' : '•••'}
          </div>

          <Link
            to="/profile"
            className={`portal-nav-link ${isActive('/profile') ? 'active' : ''}`}
            title={collapsed ? 'My Profile' : undefined}
          >
            <User size={20} weight="regular" className="portal-nav-icon" />
            {!collapsed && <span className="portal-nav-label">My Profile</span>}
          </Link>

          <Link
            to="/"
            className="portal-nav-link"
            title={collapsed ? 'Public Home' : undefined}
          >
            <House size={20} weight="regular" className="portal-nav-icon" />
            {!collapsed && <span className="portal-nav-label">Public Home</span>}
          </Link>
        </nav>

        {!collapsed && (
          <div className="portal-sidebar-footer">
            <div className="portal-status-dot-container">
              <span className="portal-status-dot" aria-hidden="true" />
              <span style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-muted)' }}>
                System Secure · UTC Active
              </span>
            </div>
          </div>
        )}
      </aside>

      {/* Main Content Viewport */}
      <section className="portal-main-content">
        {children}
      </section>
    </div>
  )
}
