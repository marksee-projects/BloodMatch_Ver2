import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { CheckCircle, ShieldCheck, Clock } from '@phosphor-icons/react'
import { useTheme } from '../context/ThemeContext'
import { useAuth } from '../context/AuthContext'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Card } from '../components/ui/Card'
import PrivacyConsentModal from '../components/PrivacyConsentModal'
import styles from './LandingPage.module.css'

export default function LandingPage() {
  const { theme } = useTheme()
  const { login } = useAuth()
  const navigate = useNavigate()
  const heroBg = theme === 'dark' ? '/Home-Page-Picture-Dark.png' : '/Home-Page-Picture-White.png'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [showPrivacyModal, setShowPrivacyModal] = useState(false)

  const onSubmit = async (e) => {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(err.message || 'Invalid email or password.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className={styles.page}>
      {/* ── Full-screen hero ── */}
      <section className={styles.hero} aria-labelledby="hero-title">
        <img
          className={styles.heroBg}
          src={heroBg}
          alt=""
          aria-hidden="true"
          loading="eager"
          decoding="async"
        />
        <div className={styles.overlay} aria-hidden="true" />

        <div className={styles.heroInner}>
          {/* LEFT: marketing copy */}
          <div className={styles.heroText}>
            <div className={styles.kicker}>Bataan · DeMolay Community Network</div>
            <h1 id="hero-title" className={styles.title}>
              Verified blood{' '}
              <span className={styles.accent}>matching</span>,<br />
              without the noise.
            </h1>
            <p className={styles.lede}>
              BloodMatch links patients with verified volunteer donors from Mt. Samat,
              Mt. Tarak, and Meridian Heights — ranked by compatibility first,
              proximity second.
            </p>
            <div className={styles.stats}>
              <span>3 chapters</span>
              <span>·</span>
              <span>8 blood types</span>
              <span>·</span>
              <span>Officer-verified</span>
            </div>
          </div>

          {/* RIGHT: login card */}
          <div className={styles.loginCard} role="complementary" aria-label="Sign in to BloodMatch">
            <div className={styles.cardHeader}>
              <h2 className={styles.cardTitle}>Sign In</h2>
            </div>

            {error && (
              <div className="alert alert-error" role="alert" style={{ marginBottom: 'var(--space-4)' }}>
                {error}
              </div>
            )}

            <form className={styles.form} onSubmit={onSubmit} noValidate>
              <Input
                label="Email"
                id="landing-email"
                type="email"
                autoComplete="email"
                placeholder="name@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              <div>
                <Input
                  label="Password"
                  id="landing-password"
                  type="password"
                  autoComplete="current-password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                />
                <Link to="/forgot-password" className={styles.forgotLink}>
                  Forgot password?
                </Link>
              </div>

              <Button
                type="submit"
                fullWidth
                isLoading={submitting}
                size="lg"
                style={{ marginTop: 'var(--space-2)' }}
              >
                {submitting ? 'Signing in…' : 'Sign in'}
              </Button>
            </form>

            <div className={styles.divider}>
              <span>or</span>
            </div>

            <Button to="/register" variant="secondary" fullWidth size="md">
              Create an account
            </Button>
          </div>
        </div>
      </section>

      {/* ── Below fold ── */}
      <div className={styles.belowFold}>
        <div className={styles.sectionLabel} id="how-it-works">How matching works</div>
        <section className={styles.grid3} aria-labelledby="how-it-works">
          <div className={styles.featureItem}>
            <CheckCircle size={48} weight="duotone" className={styles.featureIcon} />
            <h3 className={styles.featureTitle}>Red-cell first</h3>
            <p className={styles.featureText}>
              Only ABO + Rh compatible donors are considered. Proximity refines
              the eligible pool — it never overrides compatibility.
            </p>
          </div>
          <div className={styles.featureItem}>
            <ShieldCheck size={48} weight="duotone" className={styles.featureIcon} />
            <h3 className={styles.featureTitle}>Officer review</h3>
            <p className={styles.featureText}>
              Donor cards and IDs are checked by chapter officers. Every confirmed
              donation is logged in a tamper-evident trail.
            </p>
          </div>
          <div className={styles.featureItem}>
            <Clock size={48} weight="duotone" className={styles.featureIcon} />
            <h3 className={styles.featureTitle}>Protected intervals</h3>
            <p className={styles.featureText}>
              Post-donation 42-hour standby and 90-day cooldown are enforced
              automatically; availability reflects them.
            </p>
          </div>
        </section>

        {/* Medical disclaimer */}
        <section className={styles.disclaimer} role="note" aria-label="Medical Disclaimer">
          <div className={styles.disclaimerNote} aria-hidden="true">Note</div>
          <div>
            <strong>Clinical confirmation required.</strong> Suggestions are advisory
            and do not replace crossmatching, infectious screening, or physician review
            at the facility.
          </div>
        </section>

        {/* Landing-specific minimalist footer */}
        <footer className={styles.footer} aria-label="BloodMatch landing footer">
          <div className={styles.footerLeft}>
            <span className={styles.footerBrandName}>BloodMatch</span>
            <span className={styles.footerCopy}>© {new Date().getFullYear()}</span>
          </div>
          <div className={styles.footerRight}>
            <Link to="/register" className={styles.footerLink}>Join</Link>
            <a href="#how-it-works" className={styles.footerLink}>How It Works</a>
            <button 
              className={styles.footerLink} 
              style={{ background: 'none', border: 'none', padding: 0, font: 'inherit', cursor: 'pointer' }}
              onClick={() => setShowPrivacyModal(true)}
            >
              Privacy
            </button>
          </div>
        </footer>
      </div>

      <PrivacyConsentModal 
        isOpen={showPrivacyModal} 
        onClose={() => setShowPrivacyModal(false)}
        readonly 
      />
    </div>
  )
}
