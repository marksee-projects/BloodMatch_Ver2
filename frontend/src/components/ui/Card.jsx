import React from 'react';

/**
 * Standardized Card component following BloodMatch design system.
 */
export function Card({ children, className = '', padding = 'md', ...props }) {
  const paddings = {
    none: '0',
    sm: 'var(--space-3)',
    md: 'var(--space-4)',
    lg: 'var(--space-6)',
  };

  const style = {
    backgroundColor: 'var(--color-surface)',
    borderRadius: 'var(--radius-card)',
    boxShadow: '0 1px 2px rgba(15,23,42,.06), 0 6px 16px rgba(15,23,42,.06)',
    border: '1px solid var(--color-border-hairline)',
    padding: paddings[padding] || paddings.md,
    color: 'var(--color-text)',
  };

  return (
    <div className={`ui-card ${className}`} style={style} {...props}>
      {children}
    </div>
  );
}
