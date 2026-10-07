import React from 'react';

/**
 * Signature 44px rounded square block for blood types.
 * Level: 'normal' | 'urgent' | 'emergency'
 */
export function BloodTypeBlock({ bloodType, level = 'normal', className = '' }) {
  const baseStyle = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '44px',
    height: '44px',
    borderRadius: '12px', // Specific signature radius
    fontWeight: '700',
    fontSize: '18px',
    fontFamily: 'var(--font-sans)',
    flexShrink: 0,
  };

  const levels = {
    normal: {
      backgroundColor: 'rgba(2, 0, 102, 0.08)', // Navy tint
      color: 'var(--color-brand-navy)',
      border: '1px solid rgba(2, 0, 102, 0.12)',
    },
    urgent: {
      backgroundColor: 'var(--color-urgent-bg)',
      color: 'var(--color-urgent-amber)',
      border: '1px solid rgba(154, 52, 18, 0.2)',
    },
    emergency: {
      backgroundColor: 'var(--color-emergency-red)',
      color: '#FFFFFF',
      border: 'none',
    }
  };

  const style = {
    ...baseStyle,
    ...levels[level],
  };

  return (
    <div className={`ui-blood-type-block ${className}`} style={style} aria-label={`Blood type ${bloodType}, Priority: ${level}`}>
      {bloodType}
    </div>
  );
}
