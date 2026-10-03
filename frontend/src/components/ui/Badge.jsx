import React from 'react';

/**
 * Standard Badge component for roles, tags, and small indicators.
 */
export function Badge({ children, variant = 'neutral', className = '' }) {
  const baseStyle = {
    display: 'inline-flex',
    alignItems: 'center',
    padding: '2px 8px',
    borderRadius: 'var(--radius-pill)',
    fontSize: '11px',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.04em',
    whiteSpace: 'nowrap',
  };

  const variants = {
    neutral: {
      backgroundColor: 'var(--color-surface-sunken)',
      color: 'var(--color-text-muted)',
      border: '1px solid var(--color-border-hairline)',
    },
    brand: {
      backgroundColor: 'var(--color-brand-navy)',
      color: '#FFFFFF',
    },
    urgent: {
      backgroundColor: 'var(--color-urgent-bg)',
      color: 'var(--color-urgent-amber)',
    },
    critical: {
      backgroundColor: 'var(--color-critical-bg)',
      color: 'var(--color-critical-red)',
      border: '1px solid var(--color-danger-border)',
    },
    success: {
      backgroundColor: '#E6F4EA', // Assuming a light green tint
      color: 'var(--color-success-green)',
    }
  };

  const style = {
    ...baseStyle,
    ...variants[variant],
  };

  return (
    <span className={`ui-badge ${className}`} style={style}>
      {children}
    </span>
  );
}

/**
 * StatusPill for more prominent status indicators.
 */
export function StatusPill({ children, variant = 'neutral', className = '' }) {
  const baseStyle = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '6px',
    padding: '4px 12px',
    borderRadius: 'var(--radius-pill)',
    fontSize: 'var(--text-sm)',
    fontWeight: '500',
  };

  const variants = {
    neutral: {
      backgroundColor: 'var(--color-surface)',
      border: '1px solid var(--color-border-strong)',
      color: 'var(--color-text)',
    },
    active: {
      backgroundColor: '#E6F4EA',
      border: '1px solid var(--color-success-green)',
      color: 'var(--color-success-green)',
    },
    error: {
      backgroundColor: 'var(--color-critical-bg)',
      border: `1px solid var(--color-danger-border)`,
      color: 'var(--color-critical-red)',
    }
  };

  const style = {
    ...baseStyle,
    ...variants[variant],
  };

  return (
    <div className={`ui-status-pill ${className}`} style={style}>
      {children}
    </div>
  );
}
