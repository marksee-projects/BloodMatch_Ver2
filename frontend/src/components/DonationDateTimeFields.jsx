import { useId } from 'react'
import styles from './DonationDateTimeFields.module.css'

export default function DonationDateTimeFields({ value = '', onChange, legend = 'Actual donation date and time', required = true }) {
  const id = useId()
  const [date = '', time = ''] = value.split('T')
  // Reports describe completed donations, using Manila time even abroad.
  const now = new Date(Date.now() + 8 * 3600000).toISOString()
  const today = now.slice(0, 10)
  return <fieldset className={styles.fields}>
    <legend className={styles.visuallyHidden}>{legend}</legend>
    <div className={styles.grid}>
      <div><label htmlFor={`${id}-date`}>Donation date</label><input id={`${id}-date`} type="date" max={today} required={required} value={date} onChange={event => onChange(`${event.target.value}T${time}`)} /></div>
      <div><label htmlFor={`${id}-time`}>Donation time</label><input id={`${id}-time`} type="time" step="60" max={date === today ? now.slice(11, 16) : undefined} required={required} value={time.slice(0, 5)} onChange={event => onChange(`${date}T${event.target.value}`)} /></div>
    </div>
    <p className={styles.timezone}>Use the actual donation date and time (Philippine time).</p>
  </fieldset>
}
