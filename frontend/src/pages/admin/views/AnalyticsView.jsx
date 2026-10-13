import { useEffect, useState } from 'react'
import { ArrowsClockwise } from '@phosphor-icons/react'
import { api } from '../../../services/apiClient'
import { useAuth } from '../../../context/AuthContext'
import DailyTrendChart from '../../../components/charts/DailyTrendChart'
import BloodGroupDemandChart from '../../../components/charts/BloodGroupDemandChart'
import DonorPoolBreakdownChart from '../../../components/charts/DonorPoolBreakdownChart'
import styles from './AnalyticsView.module.css'
import AnalyticsPrintReport from '../../../components/AnalyticsPrintReport'
import { LoadingSpinner } from '../../../components/ui/LoadingSpinner'
import { getPhilippineDateString } from '../../../services/dateTime'

export default function AnalyticsView() {
  const { user } = useAuth()
  const [data, setData] = useState(null)
  const [chapters,setChapters] = useState([])
  const [version,setVersion] = useState(0)
  useEffect(() => { let active=true; api.get('/api/chapters').then(result => { if(active) setChapters(result.chapters || []) }).catch(() => {}); return () => { active=false } }, [])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [dateFrom, setDateFrom] = useState(() => getPhilippineDateString(-30))
  const [dateTo, setDateTo] = useState(() => getPhilippineDateString(0))
  const [chapterId, setChapterId] = useState('')
  const [activePreset, setActivePreset] = useState('monthly')

  const fetchAnalytics = () => setVersion(value => value + 1)
  useEffect(() => {
    let active=true
    setLoading(true); setError(null)
    const params=new URLSearchParams()
    if(dateFrom) params.set('date_from',dateFrom)
    if(dateTo) params.set('date_to',dateTo)
    if(chapterId) params.set('chapter_id',chapterId)
    api.get(`/api/analytics/summary?${params}`).then(result => { if(active) setData(result) })
      .catch(err => { if(active) setError(err.message || 'Failed to load analytics summary.') })
      .finally(() => { if(active) setLoading(false) })
    return () => { active=false }
  }, [dateFrom,dateTo,chapterId,version,user?.id])

  const applyPreset = (presetKey, days) => {
    setActivePreset(presetKey)
    if (days === 1) {
      setDateFrom(getPhilippineDateString(-1))
    } else {
      setDateFrom(getPhilippineDateString(-days))
    }
    setDateTo(getPhilippineDateString(0))
  }

  const reqVolume = data?.request_volume || {}
  const demandByBt = data?.demand_by_blood_type || {}
  const demandByUrg = data?.demand_by_urgency || {}
  const donorPool = data?.donor_pool || {}
  const dailyTrend = data?.daily_request_trend || []
  const metrics = [
    ['Total requests', reqVolume.total_requests || 0, `${reqVolume.total_units_requested || 0} units requested`],
    ['Fulfillment rate', `${reqVolume.fulfillment_rate_percent ?? 0}%`, `${reqVolume.fulfilled_requests || 0} of ${reqVolume.resolved_requests || 0} resolved`],
    ['Cancellation rate', `${reqVolume.cancellation_rate_percent ?? 0}%`, `${reqVolume.cancelled_requests || 0} cancelled`],
    ['Expiration rate', `${reqVolume.expiration_rate_percent ?? 0}%`, `${reqVolume.expired_requests || 0} expired`],
  ]

  // Normalized trend data for DailyTrendChart
  const chartTrend = dailyTrend.map((row) => ({
    date: row.date,
    total_requests: Number(row.requests_created || 0),
    fulfilled_requests: Number(row.fulfilled || 0)
  }))

  return (
    <div className="container" style={{ padding: 'var(--space-6) 0' }}>
      {!loading && !error && <section className={`grid-4 ${styles.metrics}`} aria-label="Request metrics">
        {metrics.map(([label,value,detail]) => <div key={label} className={`metric-card ${styles.metric}`}>
          <span className={`metric-label ${styles.metricLabel}`}>{label}</span>
          <span className="metric-value">{value}</span>
          <span className="metric-sub">{detail}</span>
        </div>)}
      </section>}

      <section className={`card ${styles.trendCard}`}>
        <h2 className={styles.trendHeading}>Request volume and fulfillment trend</h2>
      <div className={styles.toolbar}>
      {/* 1-Click Periodic Preset Tabs */}
      <div className={styles.presetsBar}>
        <div className={styles.tabGroup} role="tablist" aria-label="Reporting Intervals">
          <button
            type="button"
            role="tab"
            aria-selected={activePreset === 'daily'}
            className={`btn btn-sm ${activePreset === 'daily' ? 'btn' : 'btn-secondary'}`}
            onClick={() => applyPreset('daily', 1)}
          >
            Today (UTC)
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={activePreset === 'weekly'}
            className={`btn btn-sm ${activePreset === 'weekly' ? 'btn' : 'btn-secondary'}`}
            onClick={() => applyPreset('weekly', 7)}
          >
            Last 7 days
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={activePreset === 'monthly'}
            className={`btn btn-sm ${activePreset === 'monthly' ? 'btn' : 'btn-secondary'}`}
            onClick={() => applyPreset('monthly', 30)}
          >
            Last 30 days
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={activePreset === 'yearly'}
            className={`btn btn-sm ${activePreset === 'yearly' ? 'btn' : 'btn-secondary'}`}
            onClick={() => applyPreset('yearly', 365)}
          >
            Last 365 days
          </button>
        </div>
      </div>

      {/* Date Range & Chapter Filter Bar */}
      <div className={styles.filterBar}>
        <div className={styles.dateFilters}>
          <div className="field">
            <label htmlFor="date-from">From (UTC)</label>
            <input
              id="date-from"
              type="date"
              value={dateFrom}
              onChange={(e) => {
                setActivePreset('custom')
                setDateFrom(e.target.value)
              }}
            />
          </div>

          <div className="field">
            <label htmlFor="date-to">To (UTC)</label>
            <input
              id="date-to"
              type="date"
              value={dateTo}
              onChange={(e) => {
                setActivePreset('custom')
                setDateTo(e.target.value)
              }}
            />
          </div>

          {user?.role === 'admin' && (
            <div className="field">
              <label htmlFor="chapter-filter">Chapter</label>
              <select
                id="chapter-filter"
                value={chapterId}
                onChange={(e) => setChapterId(e.target.value)}
              >
                <option value="">All chapters</option>
                {chapters.map(chapter => <option key={chapter.id} value={chapter.id}>{chapter.name}</option>)}
              </select>
            </div>
          )}

        </div>
          <div className={styles.exportActions}>
            <a className="btn btn-secondary btn-sm" href={`/api/analytics/export?${new URLSearchParams({ date_from: dateFrom, date_to: dateTo, ...(user?.role === 'admin' && chapterId ? { chapter_id: chapterId } : {}) })}`} download>Export aggregate CSV</a>
            <button className="btn btn-secondary btn-sm" type="button" disabled={loading || Boolean(error) || !data} onClick={() => window.print()}>Print / Save PDF</button>
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={fetchAnalytics}
            >
              <ArrowsClockwise size={14} weight="regular" aria-hidden="true" /> Refresh
            </button>
          </div>
      </div>
      </div>

        {!loading && !error && <DailyTrendChart trend={chartTrend} />}
      </section>
      <AnalyticsPrintReport data={!loading && !error ? data : null} scopeLabel={data?.chapter_id == null ? 'All chapters' : (chapters.find(chapter => String(chapter.id) === String(data.chapter_id))?.name || `Chapter ${data.chapter_id}`)} />

      {error && <div className="alert alert-error" role="alert">{error}</div>}
      {loading && (
        <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
          <LoadingSpinner text="Loading analytics summary…" />
        </div>
      )}

      {!loading && !error && (
        <>
          {/* Categorical Breakdowns Grid */}
          <div className="grid-2" style={{ marginBottom: 'var(--space-6)' }}>
            {/* Blood Type Demand Distribution with Bar Chart */}
            <section className="card">
              <div className="card-header">
                <h3>Demand by Blood Group</h3>
                <span className="muted" style={{ fontSize: '0.75rem' }}>Units Requested per Type</span>
              </div>
              <BloodGroupDemandChart demandByBt={demandByBt} />
            </section>

            {/* Donor Pool Status Distribution with Segmented Gauge */}
            <section className="card">
              <div className="card-header">
                <h3>Donor Pool Availability Status</h3>
                <span className="muted" style={{ fontSize: '0.75rem' }}>Active Enrolled Pool</span>
              </div>
              <DonorPoolBreakdownChart donorPool={donorPool} />

              <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-2)', marginTop: 'var(--space-4)', paddingTop: 'var(--space-3)', borderTop: '1px solid var(--color-border-subtle)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 'var(--text-xs)' }}>
                  <span>Total Enrolled Donors:</span>
                  <strong>{donorPool.total_enrolled || 0}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 'var(--text-xs)' }}>
                  <span className="muted">Emergency Urgency Demand:</span>
                  <strong>{demandByUrg.emergency?.requests || 0} req ({demandByUrg.emergency?.units || 0}u)</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 'var(--text-xs)' }}>
                  <span className="muted">Urgent Category Demand:</span>
                  <strong>{demandByUrg.urgent?.requests || 0} req ({demandByUrg.urgent?.units || 0}u)</strong>
                </div>
              </div>
            </section>
          </div>

          {/* Detailed Daily Table */}
          <section className="card">
            <div className="card-header">
              <h3>Tabular Daily Records</h3>
              <span className="muted" style={{ fontSize: '0.75rem' }}>Exact breakdown for selected interval</span>
            </div>

            {dailyTrend.length === 0 ? (
              <p className="muted" style={{ fontSize: '0.875rem' }}>No daily request activity recorded in this date range.</p>
            ) : (
              <div className="table-container">
                <table>
                  <thead>
                    <tr>
                      <th>DATE</th>
                      <th>Requests Created</th>
                      <th>Units Requested</th>
                      <th>Fulfilled</th>
                      <th>Cancelled</th>
                      <th>Expired</th>
                    </tr>
                  </thead>
                  <tbody>
                    {dailyTrend.map((row) => (
                      <tr key={row.date}>
                        <td><code>{row.date}</code></td>
                        <td><strong>{row.requests_created}</strong></td>
                        <td>{row.units_requested}</td>
                        <td>{row.fulfilled}</td>
                        <td>{row.cancelled}</td>
                        <td>{row.expired}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </div>
  )
}
