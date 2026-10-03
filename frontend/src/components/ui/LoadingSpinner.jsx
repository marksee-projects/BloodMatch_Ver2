import React from 'react'
import { CircleNotch } from '@phosphor-icons/react'

export function LoadingSpinner({ text = 'Loading…', minHeight = '40vh' }) {
  return (
    <div 
      className="card text-center" 
      style={{ 
        padding: 'var(--space-12) var(--space-8)', 
        display: 'flex', 
        flexDirection: 'column', 
        alignItems: 'center', 
        justifyContent: 'center', 
        gap: 'var(--space-4)', 
        minHeight, 
        border: 'none', 
        background: 'transparent' 
      }}
    >
      <CircleNotch className="spinning" size={48} color="var(--color-brand-navy)" />
      {text && (
        <p className="muted" style={{ margin: 0, fontWeight: 500 }}>
          {text}
        </p>
      )}
    </div>
  )
}
