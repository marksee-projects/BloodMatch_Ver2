export default function DonorPoolBreakdownChart({ donorPool = {} }) {
  const available = Number(donorPool.available || 0)
  const standby = Number(donorPool.standby || 0)
  const cooldown = Number(donorPool.cooldown || 0)
  const unavailable = Number(donorPool.unavailable || 0)
  const total = Number(donorPool.total_enrolled || (available + standby + cooldown + unavailable) || 0)

  const segments = [
    { label: 'Available', count: available, color: 'var(--color-text)' },
    { label: 'Standby (42h)', count: standby, color: 'var(--color-text-muted)' },
    { label: 'Cooldown (90d)', count: cooldown, color: 'var(--color-text-subtle)' },
    { label: 'Unavailable', count: unavailable, color: 'var(--color-border)' }
  ]

  return (
    <div className="donor-pool-chart-wrapper" style={{ width: '100%' }}>
      {/* Proportional Segmented Bar */}
      <div
        style={{
          display: 'flex',
          height: '16px',
          width: '100%',
          borderRadius: 'var(--radius-pill)',
          overflow: 'hidden',
          background: 'var(--color-surface-hover)',
          border: '1px solid var(--color-border)',
          marginBottom: 'var(--space-3)'
        }}
        role="progressbar"
        aria-valuenow={available}
        aria-valuemin={0}
        aria-valuemax={total}
        aria-label="Donor availability distribution"
      >
        {total > 0 ? (
          segments.map((seg) => {
            if (seg.count === 0) return null
            const percent = (seg.count / total) * 100
            return (
              <div
                key={seg.label}
                title={`${seg.label}: ${seg.count} (${percent.toFixed(1)}%)`}
                style={{
                  width: `${percent}%`,
                  height: '100%',
                  background: seg.color,
                  transition: 'width var(--transition-normal)'
                }}
              />
            )
          })
        ) : (
          <div style={{ width: '100%', height: '100%', background: 'var(--color-surface-hover)' }} />
        )}
      </div>

      {/* Legend & Breakdown Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: 'var(--space-2)' }}>
        {segments.map((seg) => {
          const percent = total > 0 ? ((seg.count / total) * 100).toFixed(0) : 0
          return (
            <div
              key={seg.label}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--space-2)',
                padding: 'var(--space-2) var(--space-3)',
                borderRadius: 'var(--radius-sm)',
                background: 'var(--color-surface-sunken)',
                border: '1px solid var(--color-border-subtle)'
              }}
            >
              <span
                style={{
                  width: '8px',
                  height: '8px',
                  borderRadius: '50%',
                  background: seg.color,
                  flexShrink: 0
                }}
              />
              <div style={{ fontSize: 'var(--text-xs)', minWidth: 0 }}>
                <div style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {seg.label}
                </div>
                <div className="muted">
                  <strong>{seg.count}</strong> ({percent}%)
                </div>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
