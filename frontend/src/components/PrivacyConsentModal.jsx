import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShieldCheck } from '@phosphor-icons/react'
import styles from './PrivacyConsentModal.module.css'

export const CONSENT_STORAGE_KEY = 'bloodmatch_privacy_consent'

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
          <p style={{ marginTop: 0, fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            <em>In compliance with Republic Act No. 10173 (Data Privacy Act of 2012)</em>
          </p>

          <p style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            In compliance with Republic Act No. 10173, also known as the Data Privacy Act of 2012, BloodMatch Bataan is dedicated to protecting your personal data and safeguarding your privacy rights. To provide you with secure access to our registry and facilitate blood donation matching, we collect both personal and sensitive personal information. This includes your full name, contact details, chapter or organizational affiliation, and generalized location data such as your municipality and barangay. Additionally, we process sensitive personal information, specifically encrypted government-issued identifiers like your National ID, your blood type, and your donation history or cooldown logs.
          </p>

          <p style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            Your data is processed strictly based on your explicit consent and solely for the operational functionality of the platform. Your National ID is encrypted and accessed exclusively by verified chapter officers to confirm member identity and prevent fraudulent requests. Health data, including blood type and donation history, is used to ensure biological compatibility and enforce medically mandated cooldown periods for donor safety. Furthermore, generalized location data allows the system to calculate donor proximity during urgent emergency requests, while in-app messaging and notifications enable real-time coordination between requesters, donors, and chapter administration.
          </p>

          <p style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            We enforce strict access controls and industry-standard encryption, both in transit and at rest, to safeguard your information on a role-based, need-to-know basis. Your personal data is never sold, leased, or disclosed to third-party advertisers, and is only shared with active donors or requesters when a specific blood request is initiated and approved. We retain your personal data only as long as your account remains active or as required for historical audit logs of blood donations. Once an account is deleted or becomes permanently inactive, all personal identification is securely purged or anonymized in accordance with National Privacy Commission guidelines.
          </p>

          <p style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            <strong>Profile visibility.</strong> BloodMatch does not provide a member directory or member search. A logged-in member may view your limited account details: name, profile photo, chapter, role label, member-since date, verification status, blood type, and email address. Other members are never shown your phone number, date of birth, identification documents or OCR data, exact address, barangay, coordinates, donation history, availability, password, credentials, or security tokens.
          </p>

          <p style={{ fontSize: 'var(--text-sm)', lineHeight: 1.6 }}>
            Under Section 16 of the Data Privacy Act of 2012, you maintain full rights as a data subject. You have the right to be informed of how your data is processed, request access to your records, rectify inaccurate or outdated information, and request the erasure, blocking, or deletion of your profile from our active registry. You also reserve the right to object to data processing or withdraw your consent at any time, subject to account deactivation for active donation matching. For any privacy inquiries, concerns, or requests to exercise your data subject rights, you may contact the BloodMatch Data Privacy Team or your designated Chapter Administrator at <a href="mailto:admin@bloodmatch.org">admin@bloodmatch.org</a>.
          </p>

          {!readonly && (
            <p style={{ fontSize: 'var(--text-xs)', lineHeight: 1.5, margin: 0 }}>
              By clicking &quot;I Agree,&quot; you acknowledge that another logged-in member may see your limited account details listed above, and you consent to the processing described in this notice.
            </p>
          )}
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
