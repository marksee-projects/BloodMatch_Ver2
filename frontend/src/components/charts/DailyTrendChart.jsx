import { useState } from 'react'

export default function DailyTrendChart({ trend = [] }) {
  const [hoveredIndex, setHoveredIndex] = useState(null)

  if (!trend || trend.length === 0) {
    return (
      <div className="card text-center" style={{ padding: 'var(--space-6)', background: 'var(--color-surface-sunken)' }}>
        <p className="muted" style={{ margin: 0, fontSize: 'var(--text-sm)' }}>
          No trend activity recorded for this period.
        </p>
      </div>
    )
  }

  // Chart Dimensions
  const width = 800
  const height = 240
  const padding = { top: 30, right: 30, bottom: 40, left: 45 }

  const innerWidth = width - padding.left - padding.right
  const innerHeight = height - padding.top - padding.bottom

  const maxVal = Math.max(...trend.map((d) => Number(d.total_requests || 0)), 5)
  const yTicks = [0, Math.ceil(maxVal / 2), maxVal]

  const getX = (index) => {
    if (trend.length === 1) return padding.left + innerWidth / 2
    return padding.left + (index / (trend.length - 1)) * innerWidth
  }

  const getY = (val) => {
    return padding.top + innerHeight - (Number(val || 0) / maxVal) * innerHeight
  }

  // Build SVG Path
  const points = trend.map((d, i) => `${getX(i)},${getY(d.total_requests)}`)
  const linePath = `M ${points.join(' L ')}`
  const areaPath = `${linePath} L ${getX(trend.length - 1)},${padding.top + innerHeight} L ${getX(0)},${padding.top + innerHeight} Z`

  // Fulfilled line path
  const fulfilledPoints = trend.map((d, i) => `${getX(i)},${getY(d.fulfilled_requests || 0)}`)
  const fulfilledLinePath = `M ${fulfilledPoints.join(' L ')}`

  return (
    <div className="daily-trend-chart-wrapper" style={{ position: 'relative', width: '100%' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--space-2)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-4)', fontSize: 'var(--text-xs)' }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '12px', height: '3px', background: 'var(--color-text)', display: 'inline-block' }} />
            <strong>Total Requests</strong>
          </span>
          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '12px', height: '3px', background: 'var(--color-text-muted)', borderTop: '2px dashed var(--color-text-muted)', display: 'inline-block' }} />
            <span className="muted">Fulfilled Requests</span>
          </span>
        </div>
      </div>

      <svg
        viewBox={`0 0 ${width} ${height}`}
        style={{ width: '100%', height: 'auto', overflow: 'visible' }}
        role="img"
        aria-label="Daily request trend chart"
      >
        <defs>
          <linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--color-text)" stopOpacity="0.18" />
            <stop offset="100%" stopColor="var(--color-text)" stopOpacity="0.0" />
          </linearGradient>
        </defs>

        {/* Y Axis Gridlines & Labels */}
        {yTicks.map((tick) => {
          const y = getY(tick)
          return (
            <g key={tick}>
              <line
                x1={padding.left}
                y1={y}
                x2={width - padding.right}
                y2={y}
                stroke="var(--color-border)"
                strokeDasharray="4 4"
                strokeWidth="1"
              />
              <text
                x={padding.left - 10}
                y={y + 4}
                textAnchor="end"
                fontSize="11"
                fill="var(--color-text-muted)"
                fontFamily="var(--font-mono)"
              >
                {tick}
              </text>
            </g>
          )
        })}

        {/* Area fill */}
        <path d={areaPath} fill="url(#areaGradient)" />

        {/* Total Requests Line */}
        <path
          d={linePath}
          fill="none"
          stroke="var(--color-text)"
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {/* Fulfilled Requests Line (dashed) */}
        <path
          d={fulfilledLinePath}
          fill="none"
          stroke="var(--color-text-muted)"
          strokeWidth="2"
          strokeDasharray="4 3"
          strokeLinecap="round"
        />

        {/* Data points and hover zones */}
        {trend.map((d, i) => {
          const x = getX(i)
          const y = getY(d.total_requests)
          const isHovered = hoveredIndex === i

          // Format X label: show roughly 6-8 dates evenly spaced
          const showLabel = trend.length <= 8 || i % Math.ceil(trend.length / 7) === 0 || i === trend.length - 1

          return (
            <g key={d.date || i}>
              {/* X Axis Date Label */}
              {showLabel && (
                <text
                  x={x}
                  y={height - 12}
                  textAnchor="middle"
                  fontSize="11"
                  fill="var(--color-text-muted)"
                  fontFamily="var(--font-mono)"
                >
                  {d.date?.slice(5)}
                </text>
              )}

              {/* Point circle */}
              <circle
                cx={x}
                cy={y}
                r={isHovered ? 5 : 3.5}
                fill="var(--color-surface)"
                stroke="var(--color-text)"
                strokeWidth={isHovered ? 2.5 : 1.5}
              />

              {/* Invisible touch/hover target */}
              <rect
                x={x - innerWidth / (trend.length * 2 || 1)}
                y={padding.top}
                width={innerWidth / (trend.length || 1)}
                height={innerHeight}
                fill="transparent"
                style={{ cursor: 'pointer' }}
                onMouseEnter={() => setHoveredIndex(i)}
                onMouseLeave={() => setHoveredIndex(null)}
              />
            </g>
          )
        })}
      </svg>

      {/* Floating Hover Tooltip */}
      {hoveredIndex !== null && trend[hoveredIndex] && (
        <div
          style={{
            position: 'absolute',
            left: `${((getX(hoveredIndex) / width) * 100).toFixed(1)}%`,
            top: `${Math.max(10, getY(trend[hoveredIndex].total_requests) - 45)}px`,
            transform: 'translate(-50%, -100%)',
            background: 'var(--color-surface)',
            border: '1px solid var(--color-border-strong)',
            borderRadius: 'var(--radius-sm)',
            padding: '4px 8px',
            fontSize: 'var(--text-xs)',
            boxShadow: 'var(--shadow-md)',
            pointerEvents: 'none',
            whiteSpace: 'nowrap',
            zIndex: 10
          }}
        >
          <div style={{ fontWeight: 700, fontFamily: 'var(--font-mono)' }}>
            {trend[hoveredIndex].date}
          </div>
          <div style={{ display: 'flex', gap: '8px', marginTop: '2px' }}>
            <span>Total: <strong>{trend[hoveredIndex].total_requests || 0}</strong></span>
            <span className="muted">Fulfilled: <strong>{trend[hoveredIndex].fulfilled_requests || 0}</strong></span>
          </div>
        </div>
      )}
    </div>
  )
}
