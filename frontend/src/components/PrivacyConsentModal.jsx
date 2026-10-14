import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShieldCheck } from '@phosphor-icons/react'
import styles from './PrivacyConsentModal.module.css'

export const CONSENT_STORAGE_KEY = 'bloodmatch_privacy_consent_2026_10_14'

export default function PrivacyConsentModal({ isOpen, onConsentGranted, onDecline, onClose, readonly = false }) {
  const navigate = useNavigate()
  const modalRef = useRef(null)
  const scrollAreaRef = useRef(null)
  const [scrolledToBottom, setScrolledToBottom] = useState(false)

  const handleScroll = (e) => {
    const { scrollTop, clientHeight, scrollHeight } = e.currentTarget
    if (Math.abs(scrollHeight - clientHeight - scrollTop) <= 5) {
      setScrolledToBottom(true)
    }
  }

  // Reset scroll state when modal opens
  useEffect(() => {
    if (isOpen) {
      setScrolledToBottom(false)
      // Automatically unlock if the content fits exactly without scrolling
      setTimeout(() => {
        if (modalRef.current) {
          const scrollArea = scrollAreaRef.current
          if (scrollArea && scrollArea.scrollHeight <= scrollArea.clientHeight + 5) {
            setScrolledToBottom(true)
          }
        }
      }, 100)
    }
  }, [isOpen])

  useEffect(() => {
    if (!isOpen) return

    // Prevent body scrolling
    const originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    // Focus the notice so keyboard users can scroll it immediately.
    const timeout = setTimeout(() => {
      scrollAreaRef.current?.focus()
    }, 50)

    // Keyboard focus trap
    const handleKeyDown = (e) => {
      if (!modalRef.current) return

      if (e.key === 'Escape') {
        e.preventDefault()
        if (readonly) {
          onClose?.()
        } else if (scrolledToBottom) {
          handleDecline()
        }
        return
      }

      if (e.key === 'Tab') {
        const focusableElements = modalRef.current.querySelectorAll(
          'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'
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
  }, [isOpen, readonly, scrolledToBottom])

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
    if (onDecline) {
      onDecline()
    } else {
      navigate('/', { replace: true })
    }
  }

  return (
    <div
      className={styles.overlay}
      role="dialog"
      aria-modal="true"
      aria-labelledby="privacy-consent-title"
      aria-describedby="privacy-consent-desc"
    >
      <div className={styles.modal} ref={modalRef}>
        <div className={styles.header}>
          <div className={styles.icon} aria-hidden="true">
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

        <div
          id="privacy-consent-desc"
          ref={scrollAreaRef}
          className={styles.scrollArea}
          onScroll={handleScroll}
          tabIndex={0}
        >
          <h3>BloodMatch Bataan Privacy Notice</h3>
          <p>Under Republic Act No. 10173, or the Data Privacy Act of 2012. BloodMatch supports member verification, blood donation coordination and communication.</p>
          <ul>
            <li><strong>Information collected:</strong> Name, profile photo, contact details, chapter, date of birth, municipality/barangay, identification documents, blood type, donation history, requests and chat messages.</li>
            <li><strong>Use:</strong> Identity verification, blood-type matching, location filtering, administrative donation waiting periods, communication and notifications. BloodMatch does not replace medical screening by a blood center.</li>
            <li><strong>Profile visibility:</strong> Logged-in members may see name, photo, chapter, municipality when provided, role, member-since date, verification status and blood type. Email, phone, birth date, documents, exact address, barangay, coordinates, donation history and availability are hidden through other-member profiles.</li>
            <li><strong>Chat:</strong> Participants can read messages you send. The designated administrator may review reported messages and necessary surrounding context for abuse or safety investigations. Staff evidence access is restricted and logged. Information you share in chat is visible to recipients even when hidden from your profile.</li>
            <li><strong>Protection:</strong> Authorized staff access information necessary for verification/administration. Access controls and security measures protect stored data. Your data is not sold or leased to advertisers.</li>
            <li><strong>Retention:</strong> Necessary account information is retained while active. After identity-confirmed permanent closure, access ends immediately and profile/contact erasure is scheduled within 30 days. Identity documents are removed within 30 days after final verification/appeal. Chat records are retained for up to 12 months after last activity; necessary request/donation/audit records for up to 12 months after final resolution or the end of an active donor restriction, whichever is later. Pending cases may preserve necessary evidence. Restricted backups may retain deleted data for up to 90 additional days. Temporary deactivation and cooldown do not mean permanent closure.</li>
            <li><strong>Pending registration:</strong> No account is created until a valid email code is entered. Abandoned registration data and temporary documents are removed within 24 hours after expiry.</li>
            <li><strong>Exceptions:</strong> Limited necessary records may be retained for legal obligations or documented disputes/investigations, with restricted access.</li>
            <li><strong>Your rights:</strong> Subject to applicable conditions, request access, correction, deletion or blocking; object, withdraw consent, or complain to the National Privacy Commission. Withdrawal may affect features needing your information.</li>
            <li><strong>Contact:</strong> DeMolay Bataan, Bataan. For privacy concerns, email <a href="mailto:demolaybataan@gmail.com">demolaybataan@gmail.com</a>.</li>
          </ul>
          <p>By selecting I Agree, you consent to the processing described here, including blood-type visibility to logged-in members and restricted review of reported chat evidence.</p>
        </div>

        <div className={styles.actions}>
          {readonly ? (
            <button
              type="button"
              className="btn btn-lg"
              onClick={onClose}
              style={{ flex: 1 }}
            >
              Close
            </button>
          ) : (
            <>
              <button
                type="button"
                className="btn btn-lg"
                onClick={handleAgree}
                style={{ flex: 1 }}
                disabled={!scrolledToBottom}
              >
                I Agree
              </button>
              <button
                type="button"
                className="btn btn-lg btn-secondary"
                onClick={handleDecline}
                style={{ flex: 1 }}
                disabled={!scrolledToBottom}
              >
                I don't agree
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
