import React, { useEffect, useState } from 'react'
import { ArrowsClockwise, MapPin, Warning, Funnel } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { Card } from './ui/Card'
import { Button } from './ui/Button'
import { Badge } from './ui/Badge'
import { Link } from 'react-router-dom'

const BLOOD_TYPES = ['All', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
const URGENCIES = ['All', 'routine', 'urgent', 'critical']

export default function DemandMapWidget({ mode = 'full', showFilters = true }) {
  const [chapters, setChapters] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [selectedBloodType, setSelectedBloodType] = useState('All')
  const [selectedUrgency, setSelectedUrgency] = useState('All')
  const [selectedDays, setSelectedDays] = useState('')
  const [filtersOpen, setFiltersOpen] = useState(false)

  const isMini = mode === 'mini'

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
  const totalCritical = chapters.reduce((acc, c) => acc + (c.urgency_counts?.critical || 0), 0)
  const totalUrgent = chapters.reduce((acc, c) => acc + (c.urgency_counts?.urgent || 0), 0)
  const totalRoutine = chapters.reduce((acc, c) => acc + (c.urgency_counts?.routine || 0), 0)
  const maxRequests = Math.max(...chapters.map(c => c.open_requests_count || 0), 1)

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-4)' }}>
      {/* Header & Filter Bar (Only in Full Mode) */}
      {!isMini && showFilters && (
        <Card padding="sm" style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-4)' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 'var(--space-4)', justifyContent: 'space-between' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
              <MapPin size={20} weight="fill" color="var(--color-brand-navy)" />
              <h2 style={{ fontSize: 'var(--text-h3)', margin: 0, fontWeight: '700' }}>Regional Demand Map</h2>
            </div>
            
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
              <Button variant="secondary" size="sm" onClick={() => setFiltersOpen(!filtersOpen)}>
                <Funnel size={16} /> Filters
              </Button>
              <Button variant="secondary" size="sm" onClick={fetchMapData}>
                <ArrowsClockwise size={16} /> Refresh
              </Button>
            </div>
          </div>

          {filtersOpen && (
            <div style={{ display: 'flex', gap: 'var(--space-3)', flexWrap: 'wrap', alignItems: 'center', paddingTop: 'var(--space-2)', borderTop: '1px solid var(--color-border-hairline)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
                <label htmlFor="bt-filter" style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-subtle)', fontWeight: '600', textTransform: 'uppercase' }}>Type</label>
                <select
                  id="bt-filter"
                  value={selectedBloodType}
                  onChange={(e) => setSelectedBloodType(e.target.value)}
                  style={{ height: '32px', padding: '0 var(--space-2)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--color-border-hairline)', fontSize: 'var(--text-sm)', outline: 'none' }}
                >
                  {BLOOD_TYPES.map((bt) => <option key={bt} value={bt}>{bt}</option>)}
                </select>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
                <label htmlFor="urgency-filter" style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-subtle)', fontWeight: '600', textTransform: 'uppercase' }}>Urgency</label>
                <select
                  id="urgency-filter"
                  value={selectedUrgency}
                  onChange={(e) => setSelectedUrgency(e.target.value)}
                  style={{ height: '32px', padding: '0 var(--space-2)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--color-border-hairline)', fontSize: 'var(--text-sm)', outline: 'none' }}
                >
                  {URGENCIES.map((u) => <option key={u} value={u}>{u.charAt(0).toUpperCase() + u.slice(1)}</option>)}
                </select>
              </div>

              <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
                <label htmlFor="days-filter" style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-subtle)', fontWeight: '600', textTransform: 'uppercase' }}>Window</label>
                <select
                  id="days-filter"
                  value={selectedDays}
                  onChange={(e) => setSelectedDays(e.target.value)}
                  style={{ height: '32px', padding: '0 var(--space-2)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--color-border-hairline)', fontSize: 'var(--text-sm)', outline: 'none' }}
                >
                  <option value="">All Open</option>
                  <option value="7">Last 7 Days</option>
                  <option value="30">Last 30 Days</option>
                  <option value="90">Last 90 Days</option>
                </select>
              </div>
            </div>
          )}
        </Card>
      )}

      {/* Mini Mode Header removed; rendered by parent */}

      {/* Error state */}
      {error && <Badge variant="critical" style={{ alignSelf: 'flex-start' }}>{error}</Badge>}

      {/* Loading state: skeleton bars */}
      {loading && (
        isMini ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
            {[1, 2, 3].map((i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)' }}>
                <span style={{ width: '80px', height: '12px', borderRadius: 'var(--radius-sm)', background: 'var(--color-surface-sunken)', animation: 'pulse 1.5s ease-in-out infinite' }} />
                <span style={{ flex: 1, height: '8px', borderRadius: 'var(--radius-sm)', background: 'var(--color-surface-sunken)', animation: 'pulse 1.5s ease-in-out infinite', animationDelay: `${i * 0.15}s` }} />
                <span style={{ width: '24px', height: '12px', borderRadius: 'var(--radius-sm)', background: 'var(--color-surface-sunken)', animation: 'pulse 1.5s ease-in-out infinite' }} />
              </div>
            ))}
          </div>
        ) : (
          <Card padding="lg" style={{ textAlign: 'center' }}>
            <p style={{ color: 'var(--color-text-muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 'var(--space-2)' }}>
              <ArrowsClockwise size={16} style={{ animation: 'spin 1s linear infinite' }} /> 
              Loading regional demand…
            </p>
          </Card>
        )
      )}

      {!loading && !error && (
        <>
          {/* Empty state */}
          {chapters.length === 0 && (
            <Card padding="md" style={{ textAlign: 'center' }}>
              <p style={{ color: 'var(--color-text-muted)', fontSize: 'var(--text-sm)', margin: 0 }}>
                No active demand in your region
              </p>
            </Card>
          )}

          {chapters.length > 0 && isMini && (
            <>
              {/* Mini summary badges */}
              <div style={{ display: 'flex', gap: 'var(--space-2)', flexWrap: 'wrap' }}>
                {totalCritical > 0 && <Badge variant="critical">{totalCritical} critical</Badge>}
                {totalUrgent > 0 && <Badge variant="urgent">{totalUrgent} urgent</Badge>}
                {totalRoutine > 0 && <Badge variant="neutral">{totalRoutine} routine</Badge>}
                {totalDemand === 0 && <Badge variant="neutral">No active requests</Badge>}
              </div>

              {/* Compact bar chart rows */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
                {chapters.slice(0, 4).map((ch) => {
                  const count = ch.open_requests_count || 0
                  const pct = maxRequests > 0 ? (count / maxRequests) * 100 : 0
                  const hasCritical = (ch.urgency_counts?.critical || 0) > 0
                  const barColor = hasCritical
                    ? 'var(--color-critical-red)'
                    : (ch.urgency_counts?.urgent || 0) > 0
                      ? 'var(--color-urgent-amber)'
                      : 'var(--color-brand-navy)'

                  return (
                    <div key={ch.chapter_id} style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-3)' }}>
                      <span style={{ 
                        width: '80px', 
                        fontSize: 'var(--text-xs)', 
                        fontWeight: '500', 
                        color: 'var(--color-text-muted)',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        flexShrink: 0
                      }}>
                        {ch.chapter_name}
                      </span>
                      <div style={{ flex: 1, height: '6px', background: 'var(--color-surface-sunken)', borderRadius: '3px', overflow: 'hidden' }}>
                        <div style={{ 
                          width: `${pct}%`, 
                          height: '100%', 
                          background: barColor, 
                          borderRadius: '3px',
                          transition: 'width 0.3s ease-out'
                        }} />
                      </div>
                      <span style={{ 
                        fontSize: 'var(--text-xs)', 
                        fontWeight: '600', 
                        color: 'var(--color-text-muted)',
                        minWidth: '20px',
                        textAlign: 'right',
                        flexShrink: 0
                      }}>
                        {count}
                      </span>
                    </div>
                  )
                })}
              </div>

              <Link 
                to="/demand-map" 
                style={{ 
                  display: 'block', 
                  textAlign: 'center', 
                  fontSize: 'var(--text-sm)', 
                  color: 'var(--color-brand-navy)', 
                  textDecoration: 'none', 
                  fontWeight: '600',
                  marginTop: 'var(--space-1)'
                }}
              >
                View Full Map &rarr;
              </Link>
            </>
          )}

          {chapters.length > 0 && !isMini && (
            <>
              {/* Top Metrics Row */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 'var(--space-4)' }}>
                <Card padding="md">
                  <span style={{ fontSize: 'var(--text-xs)', textTransform: 'uppercase', color: 'var(--color-text-subtle)', fontWeight: '600' }}>Active Requests</span>
                  <p style={{ fontSize: '24px', fontWeight: '700', margin: '4px 0 0 0' }}>{totalDemand}</p>
                </Card>
                <Card padding="md">
                  <span style={{ fontSize: 'var(--text-xs)', textTransform: 'uppercase', color: 'var(--color-text-subtle)', fontWeight: '600' }}>Units Needed</span>
                  <p style={{ fontSize: '24px', fontWeight: '700', margin: '4px 0 0 0' }}>{totalUnits}</p>
                </Card>
                <Card padding="md">
                  <span style={{ fontSize: 'var(--text-xs)', textTransform: 'uppercase', color: 'var(--color-text-subtle)', fontWeight: '600' }}>Chapters Reporting</span>
                  <p style={{ fontSize: '24px', fontWeight: '700', margin: '4px 0 0 0' }}>{chapters.length}</p>
                </Card>
              </div>

              {/* Chapter cards with stacked urgency bar */}
              <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', 
                gap: 'var(--space-4)' 
              }}>
                {chapters.map((ch) => {
                  const criticalCount = ch.urgency_counts?.critical || 0
                  const urgentCount = ch.urgency_counts?.urgent || 0
                  const routineCount = ch.urgency_counts?.routine || 0
                  const total = criticalCount + urgentCount + routineCount
                  const isHighAlert = criticalCount > 0

                  return (
                    <Card
                      key={ch.chapter_id}
                      padding="md"
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 'var(--space-2)' }}>
                        <div>
                          <strong style={{ fontSize: 'var(--text-body)', fontWeight: '600' }}>{ch.chapter_name}</strong>
                          <div style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-subtle)' }}>{ch.municipality}</div>
                        </div>
                        <Badge variant={ch.open_requests_count > 0 ? (isHighAlert ? 'critical' : 'urgent') : 'neutral'}>
                          {ch.open_requests_count} req ({ch.total_units_needed}u)
                        </Badge>
                      </div>

                      {/* Stacked urgency bar */}
                      {total > 0 && (
                        <div style={{ 
                          height: '6px', 
                          background: 'var(--color-surface-sunken)', 
                          borderRadius: '3px', 
                          overflow: 'hidden',
                          display: 'flex',
                          marginBottom: 'var(--space-3)'
                        }}>
                          {criticalCount > 0 && (
                            <div style={{ width: `${(criticalCount / total) * 100}%`, height: '100%', background: 'var(--color-critical-red)' }} />
                          )}
                          {urgentCount > 0 && (
                            <div style={{ width: `${(urgentCount / total) * 100}%`, height: '100%', background: 'var(--color-urgent-amber)' }} />
                          )}
                          {routineCount > 0 && (
                            <div style={{ width: `${(routineCount / total) * 100}%`, height: '100%', background: 'var(--color-text-subtle)' }} />
                          )}
                        </div>
                      )}

                      {/* Urgency badges */}
                      <div style={{ display: 'flex', gap: 'var(--space-2)', flexWrap: 'wrap' }}>
                        {criticalCount > 0 && (
                          <Badge variant="critical">
                            <Warning size={12} weight="fill" style={{ marginRight: '4px' }} /> Critical: {criticalCount}
                          </Badge>
                        )}
                        {urgentCount > 0 && (
                          <Badge variant="urgent">
                            Urgent: {urgentCount}
                          </Badge>
                        )}
                        {routineCount > 0 && (
                          <Badge variant="neutral">
                            Routine: {routineCount}
                          </Badge>
                        )}
                        {ch.open_requests_count === 0 && (
                          <Badge variant="neutral">
                            No active demand
                          </Badge>
                        )}
                      </div>

                      <div style={{ marginTop: 'var(--space-3)', paddingTop: 'var(--space-3)', borderTop: '1px solid var(--color-border-hairline)' }}>
                        <div style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-subtle)', marginBottom: 'var(--space-2)' }}>Blood Types in Demand:</div>
                        <div style={{ display: 'flex', gap: 'var(--space-1)', flexWrap: 'wrap' }}>
                          {['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map((bt) => {
                            const units = ch.blood_type_demand?.[bt]?.units_needed || 0
                            if (units === 0) return null
                            return (
                              <span
                                key={bt}
                                style={{
                                  fontSize: '11px',
                                  fontWeight: '600',
                                  padding: '2px 6px',
                                  borderRadius: 'var(--radius-sm)',
                                  backgroundColor: 'var(--color-surface-sunken)',
                                  color: 'var(--color-text-muted)'
                                }}
                              >
                                {bt}: {units}u
                              </span>
                            )
                          })}
                        </div>
                      </div>
                    </Card>
                  )
                })}
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
