import { useId } from 'react'
import styles from './DonationDateTimeFields.module.css'
import { donationTimeToUtc } from '../services/donationCompletion'

export default function DonationDateTimeFields({ value = '', onChange, legend = 'Actual donation date and time', required = true }) {
  const id = useId()
  const [date = '', time = ''] = value.split('T')
  // Reports describe completed donations, using Manila time even abroad.
  const now = new Date(Date.now() + 8 * 3600000).toISOString()
  const today = now.slice(0, 10)
  let selected = ''
  try {
    if (date && time) selected = new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short', hour12: true }).format(new Date(donationTimeToUtc(value)))
  } catch { /* Incomplete picker values are validated on submission. */ }
  return <fieldset className={styles.fields}>
    <legend className={styles.visuallyHidden}>{legend}</legend>
    <div className={styles.grid}>
      <div><label htmlFor={`${id}-date`}>Donation date</label><input id={`${id}-date`} type="date" max={today} required={required} value={date} onChange={event => onChange(`${event.target.value}T${time}`)} /></div>
      <div><label htmlFor={`${id}-time`}>Donation time</label><input id={`${id}-time`} type="time" step="60" max={date === today ? now.slice(11, 16) : undefined} required={required} value={time.slice(0, 5)} onChange={event => onChange(`${date}T${event.target.value}`)} /></div>
    </div>
    <div className={styles.quickTime}>
      <button type="button" className="btn btn-secondary btn-sm" onClick={() => onChange(new Date(Date.now() + 8 * 3600000).toISOString().slice(0, 19))}>Use current time</button>
      <small>Use this only if you just finished donating.</small>
    </div>
    <p className={styles.timezone}>{selected ? `Donation recorded for: ${selected} (Philippine time).` : 'Enter when you actually donated, in Philippine time.'}</p>
  </fieldset>
}
