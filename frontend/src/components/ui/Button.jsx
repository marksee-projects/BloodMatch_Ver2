import React from 'react';
import { Link } from 'react-router-dom';

/**
 * Standardized Button component following BloodMatch design system.
 * Props:
 * - variant: 'primary' | 'secondary' | 'destructive' | 'dangerOutline' (default 'primary')
 * - size: 'md' | 'lg' | 'sm' (default 'md')
 * - isLoading: boolean
 * - fullWidth: boolean
 * - to: string (if provided, renders as a React Router Link)
 */
export function Button({
  children,
  variant = 'primary',
  size = 'md',
  isLoading = false,
  fullWidth = false,
  className = '',
  disabled,
  to,
  style: customStyle,
  ...props
}) {
  const baseStyles = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 'var(--space-2)',
    fontWeight: '600',
    fontFamily: 'var(--font-sans)',
    borderRadius: 'var(--radius-control)',
    transition: 'transform 100ms cubic-bezier(0.16, 1, 0.3, 1), background-color 140ms ease, opacity 140ms ease',
    cursor: disabled || isLoading ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.45 : 1,
    border: 'none',
    width: fullWidth ? '100%' : 'auto',
    textDecoration: 'none',
  };

  const variants = {
    primary: {
      '--button-bg': 'var(--color-brand-navy)',
      '--button-fg': 'var(--color-accent-fg)',
      '--button-hover-bg': 'var(--color-brand-navy-hover)',
    },
    secondary: {
      '--button-bg': 'var(--color-surface)',
      '--button-fg': 'var(--color-text)',
      '--button-hover-bg': 'var(--color-surface-sunken)',
      boxShadow: 'inset 0 0 0 1px var(--color-border-hairline)',
    },
    destructive: {
      '--button-bg': 'var(--color-emergency-red)',
      '--button-fg': 'var(--color-accent-fg)',
      '--button-hover-bg': 'var(--color-emergency-red)',
    },
    dangerOutline: {
      '--button-bg': 'var(--color-surface)',
      '--button-fg': 'var(--color-emergency-red)',
      '--button-hover-bg': 'var(--color-emergency-bg)',
      boxShadow: 'inset 0 0 0 1px var(--color-emergency-red)',
    }
  };

  const sizes = {
    sm: {
      height: '36px',
      padding: '0 var(--space-3)',
      fontSize: 'var(--text-sm)',
    },
    md: {
      height: '44px',
      padding: '0 var(--space-4)',
      fontSize: 'var(--text-body)',
    },
    lg: {
      height: '52px',
      padding: '0 var(--space-5)',
      fontSize: 'var(--text-body)',
    }
  };

  const style = {
    ...baseStyles,
    ...variants[variant],
    ...sizes[size],
    ...customStyle,
  };

  const Component = to ? Link : 'button';

  return (
    <Component
      to={to}
      className={`ui-button ${className}`}
      style={style}
      disabled={disabled || isLoading}
      aria-disabled={disabled || isLoading || undefined}
      {...props}
    >
      {isLoading ? <span className="spinner" aria-hidden="true" style={{ width: '1em', height: '1em', border: '2px solid currentColor', borderRightColor: 'transparent', borderRadius: '50%', animation: 'spin 0.75s linear infinite' }} /> : null}
      {children}
    </Component>
  );
}
