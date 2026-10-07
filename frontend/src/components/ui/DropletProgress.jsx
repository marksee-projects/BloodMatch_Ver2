import React from 'react';
import { Drop } from '@phosphor-icons/react'; // Ensure phosphor-icons is available or substitute with Lucide

/**
 * Visual indicator showing units needed vs. collected.
 */
export function DropletProgress({ totalUnits, collectedUnits = 0, isEmergency = false, className = '' }) {
  const droplets = [];
  
  for (let i = 0; i < totalUnits; i++) {
    const isFilled = i < collectedUnits;
    droplets.push(
      <span 
        key={i} 
        style={{
          color: isFilled 
            ? (isEmergency ? 'var(--color-emergency-red)' : 'var(--color-brand-navy)')
            : 'var(--color-border-strong)',
          transition: 'color 0.3s ease',
          display: 'inline-flex',
        }}
      >
        <Drop size={18} weight={isFilled ? 'fill' : 'regular'} />
      </span>
    );
  }

  return (
    <div 
      className={`ui-droplet-progress ${className}`} 
      style={{ display: 'flex', gap: '4px', alignItems: 'center' }}
      aria-label={`${collectedUnits} out of ${totalUnits} units collected`}
    >
      {droplets}
    </div>
  );
}
