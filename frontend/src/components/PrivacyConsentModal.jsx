import { useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShieldCheck } from '@phosphor-icons/react'

export const CONSENT_STORAGE_KEY = 'bloodmatch_privacy_consent'

export default function PrivacyConsentModal({ isOpen, onConsentGranted }) {
  const navigate = useNavigate()
  const modalRef = useRef(null)
  const agreeBtnRef = useRef(null)

  useEffect(() => {
    if (!isOpen) return

    // Prevent body scrolling
    const originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    // Focus primary action on mount
    const timeout = setTimeout(() => {
      agreeBtnRef.current?.focus()
    }, 50)

    // Keyboard focus trap
    const handleKeyDown = (e) => {
      if (!modalRef.current) return

      if (e.key === 'Escape') {
        e.preventDefault()
        handleDecline()
        return
      }

      if (e.key === 'Tab') {
        const focusableElements = modalRef.current.querySelectorAll(
          'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )
        const firstElement = focusableElements[0]
        const lastElement = focusableElements[focusableElements.length - 1]

        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            e.preventDefault()
            lastElement?.focus()
          }
        } else {
          if (document.activeElement === lastElement) {
            e.preventDefault()
            firstElement?.focus()
          }
        }
      }
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      clearTimeout(timeout)
      document.body.style.overflow = originalOverflow
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [isOpen])

  if (!isOpen) return null

  const handleAgree = () => {
    try {
      localStorage.setItem(CONSENT_STORAGE_KEY, 'granted')
    } catch {
      // Storage write fallback
    }
    if (onConsentGranted) onConsentGranted()
  }

  const handleDecline = () => {
    try {
      localStorage.removeItem(CONSENT_STORAGE_KEY)
    } catch {
      // Storage fallback
    }
    navigate('/', { replace: true })
  }

  return (
    <div
      className="privacy-shield-overlay"
      role="dialog"
      aria-modal="true"
      aria-labelledby="privacy-consent-title"
      aria-describedby="privacy-consent-desc"
    >
      <div className="privacy-consent-modal" ref={modalRef}>
        <div className="privacy-modal-header">
          <div className="privacy-modal-icon" aria-hidden="true">
            <ShieldCheck size={28} weight="fill" />
          </div>
          <div>
            <h2 id="privacy-consent-title" style={{ margin: 0, fontSize: 'var(--text-h2)' }}>
              Privacy &amp; Data Processing Consent
            </h2>
            <div className="muted" style={{ fontSize: 'var(--text-xs)', marginTop: '2px' }}>
              DeMolay Bataan BloodMatch Security &amp; Compliance Policy
            </div>
          </div>
        </div>

        <div id="privacy-consent-desc" className="privacy-modal-body">
          <p style={{ marginTop: 0, fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            To provide you with secure access to the BloodMatch dashboard and real-time matching features, we require your consent to process specific information.
          </p>

          <h4 style={{ margin: 'var(--space-3) 0 var(--space-2)', fontSize: 'var(--text-sm)', textTransform: 'uppercase', letterSpacing: '0.04em' }}>
            How We Use Your Data:
          </h4>

          <div className="privacy-items-list">
            <div className="privacy-item">
              <strong>Location Data (Demand Map):</strong> We use your generalized location to populate the Regional Demand Map, calculate proximity to local chapters, and highlight nearby blood requests.
            </div>

            <div className="privacy-item">
              <strong>Identity Verification:</strong> Your uploaded National ID is stored securely and accessed exclusively by verified chapter officers to confirm your identity and maintain system integrity.
            </div>

            <div className="privacy-item">
              <strong>Health &amp; Compatibility Data:</strong> Your blood type and donation history are processed by our matching engine strictly to ensure safe biological compatibility and enforce mandatory post-donation cooldown periods.
            </div>
          </div>

          <p className="privacy-note" style={{ fontSize: 'var(--text-xs)', color: 'var(--color-text-muted)', margin: 'var(--space-3) 0' }}>
            Your data is used solely for the operational functionality of the BloodMatch system and is never shared with third-party advertisers.
          </p>

          <p style={{ fontSize: 'var(--text-xs)', lineHeight: 1.5, margin: 0 }}>
            By clicking &quot;I Agree,&quot; you consent to these terms and unlock the dashboard. If you click &quot;Decline,&quot; you will be safely redirected to the public homepage, and no tracking or dashboard data processing will occur.
          </p>
        </div>

        <div className="privacy-modal-actions">
          <button
            ref={agreeBtnRef}
            type="button"
            className="btn btn-lg"
            onClick={handleAgree}
            style={{ flex: 1 }}
          >
            I Agree
          </button>
          <button
            type="button"
            className="btn btn-lg btn-secondary"
            onClick={handleDecline}
            style={{ flex: 1 }}
          >
            Decline &amp; Return to Home
          </button>
        </div>
      </div>
    </div>
  )
}
