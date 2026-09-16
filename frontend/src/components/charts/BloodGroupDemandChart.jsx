import { useState } from 'react'

const ALL_BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

export default function BloodGroupDemandChart({ demandByBt = {} }) {
  const [hoveredBt, setHoveredBt] = useState(null)

  const items = ALL_BLOOD_TYPES.map((bt) => {
    const data = demandByBt[bt] || {}
    return {
      bloodType: bt,
      requestsCount: Number(data.requests_count || 0),
      unitsNeeded: Number(data.units_needed || 0)
    }
  })

  const maxUnits = Math.max(...items.map((i) => i.unitsNeeded), 5)

  return (
    <div className="blood-group-chart-wrapper" style={{ width: '100%' }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(8, 1fr)', gap: 'var(--space-2)', alignItems: 'flex-end', height: '180px', paddingBottom: 'var(--space-2)', borderBottom: '1px solid var(--color-border)' }}>
        {items.map((item) => {
          const heightPercent = maxUnits > 0 ? (item.unitsNeeded / maxUnits) * 100 : 0
          const isHovered = hoveredBt === item.bloodType
          const hasDemand = item.unitsNeeded > 0

          return (
            <div
              key={item.bloodType}
              style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'flex-end',
                height: '100%',
                position: 'relative',
                cursor: 'pointer'
              }}
              onMouseEnter={() => setHoveredBt(item.bloodType)}
              onMouseLeave={() => setHoveredBt(null)}
            >
              {/* Value label on top of bar */}
              <div
                style={{
                  fontSize: '0.7rem',
                  fontFamily: 'var(--font-mono)',
                  fontWeight: 700,
                  marginBottom: '4px',
                  color: hasDemand ? 'var(--color-text)' : 'var(--color-text-subtle)',
                  opacity: hasDemand || isHovered ? 1 : 0.4
                }}
              >
                {item.unitsNeeded > 0 ? `${item.unitsNeeded}u` : '0'}
              </div>

              {/* Bar */}
              <div
                style={{
                  width: '70%',
                  minHeight: '4px',
                  height: `${Math.max(4, heightPercent)}%`,
                  borderRadius: 'var(--radius-sm) var(--radius-sm) 0 0',
                  background: hasDemand
                    ? isHovered
                      ? 'var(--color-accent-hover)'
                      : 'var(--color-accent)'
                    : 'var(--color-surface-hover)',
                  border: '1px solid var(--color-border)',
                  borderBottom: 'none',
                  transition: 'height var(--transition-normal), background var(--transition-fast)'
                }}
              />

              {/* Tooltip on hover */}
              {isHovered && (
                <div
                  style={{
                    position: 'absolute',
                    bottom: '100%',
                    marginBottom: '8px',
                    background: 'var(--color-surface)',
                    border: '1px solid var(--color-border-strong)',
                    borderRadius: 'var(--radius-sm)',
                    padding: '4px 8px',
                    fontSize: 'var(--text-xs)',
                    boxShadow: 'var(--shadow-md)',
                    whiteSpace: 'nowrap',
                    zIndex: 20,
                    pointerEvents: 'none'
                  }}
                >
                  <strong>{item.bloodType}</strong>: {item.unitsNeeded} units needed ({item.requestsCount} requests)
                </div>
              )}
            </div>
          )
        })}
      </div>

      {/* X Axis Labels */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(8, 1fr)', gap: 'var(--space-2)', marginTop: 'var(--space-2)', textAlign: 'center' }}>
        {items.map((item) => (
          <div
            key={item.bloodType}
            style={{
              fontSize: 'var(--text-xs)',
              fontWeight: 700,
              fontFamily: 'var(--font-mono)',
              color: hoveredBt === item.bloodType ? 'var(--color-text)' : 'var(--color-text-muted)'
            }}
          >
            {item.bloodType}
          </div>
        ))}
      </div>
    </div>
  )
}
