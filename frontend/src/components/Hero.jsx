import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import styles from './Hero.module.css'

export default function Hero() {
  const { user } = useAuth()

  return (
    <section className={styles.hero} aria-labelledby="hero-title">
      <div className={styles.kicker}>Bataan · DeMolay Community Network</div>
      <h1 id="hero-title" className={styles.title}>
        Verified blood <span className={styles.accent}>matching</span>,<br />without the noise.
      </h1>
      <p className={styles.lede}>
        BloodMatch links patients who need blood with verified volunteer donors from
        Mt. Samat, Mt. Tarak and Meridian Heights, ranked by compatibility first, proximity second.
      </p>
      <div className={styles.actions}>
        {user ? (
          <>
            <Link to="/requests/new" className="btn btn-lg">Create Blood Request</Link>
            <Link to="/profile" className="btn btn-secondary">Manage Profile</Link>
            {user.role === 'officer' && <Link to="/officer/dashboard" className="btn btn-secondary">Officer Dashboard</Link>}
            {user.role === 'admin' && <Link to="/admin/dashboard" className="btn btn-secondary">Admin Dashboard</Link>}
          </>
        ) : (
          <>
            <Link to="/register" className="btn btn-lg">Register as Donor</Link>
            <Link to="/login" className="btn btn-secondary">Sign In</Link>
          </>
        )}
      </div>
      <div className={styles.meta}>
        <span>3 chapters</span>
        <span>·</span>
        <span>8 blood types</span>
        <span>·</span>
        <span>Officer-verified</span>
      </div>
    </section>
  )
}
