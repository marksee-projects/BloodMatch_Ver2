import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../../context/AuthContext'
import { api } from '../../../services/apiClient'
import { liveQueryOptions } from '../../../services/liveQueries'
import { parseAdminDashboard } from '../../../services/adminDashboard'
import { LoadingSpinner } from '../../../components/ui/LoadingSpinner'
import { Button } from '../../../components/ui/Button'
import styles from './DashboardView.module.css'

export default function DashboardView() {
  const { user } = useAuth()
  const query = useQuery({ ...liveQueryOptions, queryKey: ['admin-dashboard', user?.id], enabled: user?.role === 'admin', queryFn: async () => parseAdminDashboard(await api.get('/api/admin/dashboard')) })
  if (query.isPending) return <LoadingSpinner text="Loading dashboard..." />
  if (!query.data) return <div className="alert alert-error" role="alert"><p>{query.error?.message || 'Dashboard unavailable.'}</p><Button variant="secondary" onClick={() => query.refetch()}>Retry</Button></div>
  const { overview, donor_pool: pool, recent_system_activity: logs = [] } = query.data
  const rates = [
    ['Fulfilled', overview.fulfilled_requests, overview.fulfillment_rate_percent],
    ['Expired', overview.expired_requests, overview.expiration_rate_percent],
    ['Cancelled', overview.cancelled_requests, overview.cancellation_rate_percent],
  ]
  return <div className={'container ' + styles.page}>
    <h1 className="sr-only">Dashboard</h1>
    {query.error && <div role="alert" className="alert alert-error">Updates unavailable. Showing the last loaded metrics. <Button variant="secondary" onClick={() => query.refetch()}>Retry</Button></div>}
    <section className={styles.metrics} aria-label="System totals">
      <Metric label="Total users" value={overview.total_users} detail={overview.total_members + ' members / ' + overview.total_staff + ' staff'} />
      <Metric label="Enrolled donors" value={overview.enrolled_donors} detail={overview.available_donors + ' currently available'} />
      <Metric label="Open requests" value={overview.active_open_requests} detail={overview.open_units_needed + ' units needed'} />
      <Metric label="Fulfillment rate" value={overview.resolved_requests ? overview.fulfillment_rate_percent + '%' : '—'} detail={overview.resolved_requests ? overview.fulfilled_requests + ' of ' + overview.resolved_requests + ' resolved requests' : 'No resolved requests yet'} />
    </section>
    <div className={styles.columns}>
      <section className={styles.card}><h2>Request outcomes</h2>{!overview.resolved_requests && <p>No resolved requests yet.</p>}<div className={styles.rates}>{rates.map(([label, count, rate]) => <div key={label}><div className={styles.rateLabel}><span>{label}</span><strong>{rate}% <span className={styles.muted}>({count})</span></strong></div><progress aria-label={label + ' requests'} max="100" value={rate} /></div>)}</div></section>
      <section className={styles.card}><h2>Donor availability</h2><dl className={styles.pool}>{['available', 'standby', 'cooldown', 'unavailable'].map(key => <div key={key}><dt>{key.charAt(0).toUpperCase() + key.slice(1)}</dt><dd>{pool[key]}</dd></div>)}</dl></section>
    </div>
    <section className={styles.card}><div className={styles.activityHeader}><h2>Recent system activity</h2><Link to="/admin/audit-logs">View audit logs</Link></div>{!logs.length ? <p className={styles.muted}>No recent activity recorded.</p> : <div className="table-container"><table><thead><tr><th>Time (UTC)</th><th>Action</th><th>Actor</th><th>Record</th></tr></thead><tbody>{logs.map(log => <tr key={log.id}><td>{log.created_at}</td><td>{log.action}</td><td>{log.actor_name || 'System / former member'}</td><td>{log.target_type || '—'}</td></tr>)}</tbody></table></div>}</section>
  </div>
}
function Metric({ label, value, detail }) { return <div className={styles.card}><span className={styles.muted}>{label}</span><strong className={styles.value}>{value}</strong><span className={styles.muted}>{detail}</span></div> }
