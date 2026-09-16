import { useEffect, useState } from 'react'
import { ArrowsClockwise, MapPin, Warning } from '@phosphor-icons/react'
import { api } from '../services/apiClient'

const BLOOD_TYPES = ['All', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const URGENCIES = ['All', 'routine', 'urgent', 'critical']

export default function DemandMapWidget({ compact = false, showFilters = true }) {
  const [chapters, setChapters] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [selectedBloodType, setSelectedBloodType] = useState('All')
  const [selectedUrgency, setSelectedUrgency] = useState('All')
  const [selectedDays, setSelectedDays] = useState('')

  const fetchMapData = async () => {
    setLoading(true)
    setError(null)
    try {
      const params = new URLSearchParams()
      if (selectedBloodType !== 'All') params.set('blood_type', selectedBloodType)
      if (selectedUrgency !== 'All') params.set('urgency', selectedUrgency)
      if (selectedDays) params.set('days', selectedDays)

      const res = await api.get(`/api/demand-map?${params.toString()}`)
      setChapters(res.chapters || [])
    } catch (err) {
      setError(err.message || 'Failed to load regional demand map.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchMapData()
  }, [selectedBloodType, selectedUrgency, selectedDays])

  const totalDemand = chapters.reduce((acc, c) => acc + (c.open_requests_count || 0), 0)
  const totalUnits = chapters.reduce((acc, c) => acc + (c.total_units_needed || 0), 0)

  return (
    <div className="demand-map-widget-container">
      <div className="demand-map-widget">
        {/* Header & Filter Bar */}
        {showFilters && (
          <section className="card" style={{ marginBottom: 'var(--space-4)' }}>
            <div style={{ display: 'flex', gap: 'var(--space-3)', flexWrap: 'wrap', alignItems: 'flex-end', justifyContent: 'space-between' }}>
              <div style={{ display: 'flex', gap: 'var(--space-3)', flexWrap: 'wrap', flex: 1 }}>
                <div className="field" style={{ minWidth: '120px', flex: 1 }}>
                  <label htmlFor="bt-filter" style={{ fontSize: 'var(--text-xs)' }}>Blood Type</label>
                  <select
                    id="bt-filter"
                    value={selectedBloodType}
                    onChange={(e) => setSelectedBloodType(e.target.value)}
                  >
                    {BLOOD_TYPES.map((bt) => (
                      <option key={bt} value={bt}>{bt}</option>
                    ))}
                  </select>
                </div>

                <div className="field" style={{ minWidth: '120px', flex: 1 }}>
                  <label htmlFor="urgency-filter" style={{ fontSize: 'var(--text-xs)' }}>Urgency</label>
                  <select
                    id="urgency-filter"
                    value={selectedUrgency}
                    onChange={(e) => setSelectedUrgency(e.target.value)}
                  >
                    {URGENCIES.map((u) => (
                      <option key={u} value={u}>{u.charAt(0).toUpperCase() + u.slice(1)}</option>
                    ))}
                  </select>
                </div>

                <div className="field" style={{ minWidth: '130px', flex: 1 }}>
                  <label htmlFor="days-filter" style={{ fontSize: 'var(--text-xs)' }}>Window</label>
                  <select
                    id="days-filter"
                    value={selectedDays}
                    onChange={(e) => setSelectedDays(e.target.value)}
                  >
                    <option value="">All Open</option>
                    <option value="7">Last 7 Days</option>
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                  </select>
                </div>
              </div>

              <div>
                <button
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={fetchMapData}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                >
                  <ArrowsClockwise size={14} weight="regular" /> Refresh
                </button>
              </div>
            </div>
          </section>
        )}

        {/* Error state */}
        {error && <div className="alert alert-error" role="alert">{error}</div>}

        {/* Loading state */}
        {loading && (
          <div className="card text-center" style={{ padding: 'var(--space-6)' }}>
            <p className="muted" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 'var(--space-2)' }}>
              <ArrowsClockwise size={16} className="spinning" /> Loading regional blood demand map…
            </p>
          </div>
        )}

        {!loading && !error && (
          <>
            {/* Top Metrics Row */}
            <div className="grid-3" style={{ marginBottom: 'var(--space-4)' }}>
              <div className="metric-card">
                <span className="metric-label">Active Requests</span>
                <span className="metric-value">{totalDemand}</span>
                <span className="metric-sub">Across Bataan</span>
              </div>
              <div className="metric-card">
                <span className="metric-label">Units Needed</span>
                <span className="metric-value">{totalUnits}</span>
                <span className="metric-sub">Open patient demand</span>
              </div>
              <div className="metric-card">
                <span className="metric-label">Chapters Reporting</span>
                <span className="metric-value">{chapters.length}</span>
                <span className="metric-sub">Regional centroids</span>
              </div>
            </div>

            {/* Visual Bataan Chapter Centroid Overview */}
            <div className="card" style={{ marginBottom: 'var(--space-4)', background: 'var(--color-surface-sunken)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', marginBottom: 'var(--space-3)' }}>
                <MapPin size={18} weight="fill" />
                <h3 style={{ margin: 0, fontSize: 'var(--text-h3)' }}>Bataan Peninsula Chapter Locations</h3>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 'var(--space-3)' }}>
                {chapters.map((ch) => {
                  const criticalCount = ch.urgency_counts?.critical || 0
                  const urgentCount = ch.urgency_counts?.urgent || 0
                  const isHighAlert = criticalCount > 0

                  return (
                    <div
                      key={ch.chapter_id}
                      className="card"
                      style={{
                        padding: 'var(--space-3)',
                        borderLeft: isHighAlert ? '4px solid var(--color-danger)' : '4px solid var(--color-accent)'
                      }}
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 'var(--space-2)' }}>
                        <div>
                          <strong style={{ fontSize: 'var(--text-sm)' }}>{ch.chapter_name}</strong>
                          <div className="muted" style={{ fontSize: 'var(--text-xs)' }}>{ch.municipality}</div>
                        </div>
                        <span className="badge" style={{ fontWeight: 700 }}>
                          {ch.open_requests_count} req ({ch.total_units_needed} units)
                        </span>
                      </div>

                      {/* Urgency badges */}
                      <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                        {ch.urgency_counts?.critical > 0 && (
                          <span className="badge badge-critical" style={{ fontSize: '0.65rem' }}>
                            <Warning size={10} weight="fill" /> Critical: {ch.urgency_counts.critical}
                          </span>
                        )}
                        {ch.urgency_counts?.urgent > 0 && (
                          <span className="badge badge-urgent" style={{ fontSize: '0.65rem' }}>
                            Urgent: {ch.urgency_counts.urgent}
                          </span>
                        )}
                        {ch.urgency_counts?.routine > 0 && (
                          <span className="badge badge-routine" style={{ fontSize: '0.65rem' }}>
                            Routine: {ch.urgency_counts.routine}
                          </span>
                        )}
                        {ch.open_requests_count === 0 && (
                          <span className="badge" style={{ fontSize: '0.65rem', background: 'var(--color-surface-hover)' }}>
                            No active demand
                          </span>
                        )}
                      </div>

                      {!compact && (
                        <div style={{ marginTop: 'var(--space-3)', paddingTop: 'var(--space-2)', borderTop: '1px solid var(--color-border-subtle)' }}>
                          <div className="muted" style={{ fontSize: '0.7rem', marginBottom: '4px' }}>Blood Types in Demand:</div>
                          <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                            {['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map((bt) => {
                              const units = ch.blood_type_demand?.[bt]?.units_needed || 0
                              if (units === 0) return null
                              return (
                                <span
                                  key={bt}
                                  style={{
                                    fontSize: '0.7rem',
                                    fontWeight: 600,
                                    padding: '1px 6px',
                                    borderRadius: 'var(--radius-sm)',
                                    background: 'var(--color-surface)',
                                    border: '1px solid var(--color-border)'
                                  }}
                                >
                                  {bt}: {units}u
                                </span>
                              )
                            })}
                          </div>
                        </div>
                      )}
                    </div>
                  )
                })}
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
