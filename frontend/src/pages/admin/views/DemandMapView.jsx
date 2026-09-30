import { Link } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext'
import DemandMapWidget from '../../../components/DemandMapWidget'

export default function DemandMapView() {
  const { user } = useAuth()

  return (
    <div className="container">
      <header className="app-header">
        <div>
          <h1>Regional Blood Demand Map</h1>
          <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
            Aggregated real-time patient blood requests across Bataan chapters and municipalities.
          </p>
        </div>
        <div className="button-group">
          {user?.role === 'officer' && (
            <Link to="/officer/dashboard" className="btn btn-secondary btn-sm">
              Officer Dashboard
            </Link>
          )}
          {user?.role === 'admin' && (
            <Link to="/admin/dashboard" className="btn btn-secondary btn-sm">
              Admin Dashboard
            </Link>
          )}
        </div>
      </header>

      <DemandMapWidget compact={false} showFilters={true} />
    </div>
  )
}
