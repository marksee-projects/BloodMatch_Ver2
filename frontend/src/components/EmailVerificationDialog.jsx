import { useEffect, useState } from 'react'
import { CheckCircle, WarningCircle, X } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import styles from './EmailVerificationDialog.module.css'

export default function EmailVerificationDialog({ isOpen, onClose, onSuccess, initialEmail = '' }) {
  const [code, setCode] = useState('')
  const [status, setStatus] = useState('idle') // idle, sending, error, success, rate_limited
  const [errorMsg, setErrorMsg] = useState('')
  const [countdown, setCountdown] = useState(0)

  useEffect(() => {
    if (!isOpen) {
      setCode('')
      setStatus('idle')
      setErrorMsg('')
    }
  }, [isOpen])

  useEffect(() => {
    let timer
    if (countdown > 0) {
      timer = setInterval(() => setCountdown(c => c - 1), 1000)
    }
    return () => clearInterval(timer)
  }, [countdown])

  const handleVerify = async (e) => {
    e.preventDefault()
    if (!code.trim()) return
    setStatus('sending')
    try {
      await api.post('/api/auth/verify', { code: code.trim() })
      setStatus('success')
      if (onSuccess) {
        setTimeout(onSuccess, 1500)
      }
    } catch (err) {
      setStatus('error')
      setErrorMsg(err.message || 'Verification failed. Please check the code and try again.')
    }
  }

  const handleResend = async () => {
    if (countdown > 0 || status === 'sending') return
    setStatus('sending')
    try {
      await api.post('/api/auth/verify/resend')
      setStatus('idle')
      setCountdown(60)
      setCode('')
      setErrorMsg('')
    } catch (err) {
      if (err.status === 429) {
        setStatus('rate_limited')
        setErrorMsg('Too many resend attempts. Please wait and try again later.')
      } else {
        setStatus('error')
        setErrorMsg(err.message || 'Failed to resend code.')
      }
    }
  }

  if (!isOpen) return null

  return (
    <div className={styles.overlay} onClick={onClose} role="dialog" aria-modal="true" aria-labelledby="dialog-title">
      <div className={styles.dialog} onClick={e => e.stopPropagation()}>
        <button className={styles.closeButton} onClick={onClose} aria-label="Close dialog">
          <X size={24} />
        </button>

        {status === 'success' ? (
          <div className={styles.successState}>
            <CheckCircle size={48} weight="fill" className={styles.successIcon} />
            <h2 id="dialog-title">Email Verified!</h2>
            <p>Your email has been successfully verified.</p>
          </div>
        ) : (
          <div className={styles.verifyState}>
            <h2 id="dialog-title">Verify Your Email</h2>
            <p>We've sent a verification code to your email address {initialEmail && <strong>{initialEmail}</strong>}. Please enter it below.</p>
            
            <form onSubmit={handleVerify} className={styles.form}>
              <div className={styles.inputGroup}>
                <label htmlFor="otp-code" className="sr-only">Verification Code</label>
                <input
                  id="otp-code"
                  type="text"
                  placeholder="Enter 6-digit code"
                  value={code}
                  onChange={e => setCode(e.target.value)}
                  className={styles.input}
                  maxLength={6}
                  autoComplete="one-time-code"
                />
              </div>

              {errorMsg && (
                <div className={styles.errorMessage} role="alert">
                  <WarningCircle size={20} />
                  <span>{errorMsg}</span>
                </div>
              )}

              <button 
                type="submit" 
                className={styles.submitButton}
                disabled={!code.trim() || status === 'sending'}
              >
                {status === 'sending' ? 'Verifying...' : 'Verify Email'}
              </button>
            </form>

            <div className={styles.resendSection}>
              <p>Didn't receive the code?</p>
              <button 
                onClick={handleResend} 
                disabled={countdown > 0 || status === 'sending' || status === 'rate_limited'}
                className={styles.resendButton}
              >
                {countdown > 0 ? `Resend available in ${countdown}s` : 'Resend Code'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
