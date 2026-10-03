import React, { forwardRef } from 'react';

export const Input = forwardRef(({ className = '', error, label, ...props }, ref) => {
  const containerStyle = {
    display: 'flex',
    flexDirection: 'column',
    gap: 'var(--space-2)',
    width: '100%',
  };

  const labelStyle = {
    fontSize: 'var(--text-sm)',
    fontWeight: '500',
    color: 'var(--color-text-secondary)',
  };

  const inputStyle = {
    height: '44px',
    padding: '0 var(--space-4)',
    borderRadius: 'var(--radius-control)',
    border: `1px solid ${error ? 'var(--color-critical-red)' : 'var(--color-border-strong)'}`,
    backgroundColor: 'var(--color-surface)',
    color: 'var(--color-text)',
    fontSize: 'var(--text-body)',
    fontFamily: 'var(--font-sans)',
    outline: 'none',
    transition: 'border-color 0.2s, box-shadow 0.2s',
  };

  const errorStyle = {
    fontSize: 'var(--text-xs)',
    color: 'var(--color-critical-red)',
    marginTop: 'var(--space-1)',
  };

  return (
    <div style={containerStyle} className={className}>
      {label && <label style={labelStyle}>{label}</label>}
      <input 
        ref={ref}
        style={inputStyle}
        {...props}
      />
      {error && <span style={errorStyle}>{error}</span>}
    </div>
  );
});

Input.displayName = 'Input';
