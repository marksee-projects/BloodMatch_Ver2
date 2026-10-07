import { Link } from 'react-router-dom'

export default function Footer() {
  return (
    <footer className="site-footer" role="contentinfo" aria-label="Site footer">
      <div className="site-footer-inner">
        <div className="site-footer-columns">

          {/* Col 1: Brand */}
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)', marginBottom: 'var(--space-2)' }}>
              <img src="/favicon-demolay.png?v=2" alt="BloodMatch" style={{ width: 20, height: 20, borderRadius: '50%' }} />
              <strong style={{ fontSize: 'var(--text-sm)' }}>BloodMatch</strong>
            </div>
            <p className="site-footer-brand-tagline">
              A verified blood-donor matching platform for the Bataan DeMolay community.
              Connecting patients and donors across Mt. Samat, Mt. Tarak, and Meridian Heights.
            </p>
          </div>

          {/* Col 2: Platform */}
          <div>
            <div className="site-footer-col-title">Platform</div>
            <ul className="site-footer-col-links">
              <li><Link to="/">Home Feed</Link></li>
              <li><Link to="/profile">My Requests</Link></li>
              <li><Link to="/notifications">Notifications</Link></li>
              <li><Link to="/profile">Profile</Link></li>
            </ul>
          </div>

          {/* Col 3: Info */}
          <div>
            <div className="site-footer-col-title">Info</div>
            <ul className="site-footer-col-links">
              <li><a href="#how-it-works">How It Works</a></li>
              <li><Link to="/privacy">Privacy Notice</Link></li>
              <li><a href="mailto:support@bloodmatch.ph">Contact Support</a></li>
            </ul>
          </div>
        </div>

        <div className="site-footer-bottom">
          <p className="site-footer-primary">
            © {new Date().getFullYear()} BloodMatch · Bataan DeMolay · Community project · Not a substitute for medical advice
          </p>
        </div>
      </div>
    </footer>
  )
}
