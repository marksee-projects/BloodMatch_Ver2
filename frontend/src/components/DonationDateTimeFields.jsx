import { useId } from 'react'
import styles from './DonationDateTimeFields.module.css'

export default function DonationDateTimeFields({ value = '', onChange, legend = 'Actual donation date and time', required = true }) {
  const id = useId()
  const [date = '', time = ''] = value.split('T')
  return <fieldset className={styles.fields} aria-describedby={`${id}-zone`}>
    <legend className={styles.visuallyHidden}>{legend}</legend>
    <p id={`${id}-zone`} className={styles.timezone}>Asia/Manila (UTC+08:00)</p>
    <div className={styles.grid}>
      <div><label htmlFor={`${id}-date`}>Donation date</label><input id={`${id}-date`} type="date" required={required} value={date} onChange={event => onChange(`${event.target.value}T${time}`)} /></div>
      <div><label htmlFor={`${id}-time`}>Donation time</label><input id={`${id}-time`} type="time" step="1" required={required} value={time} onChange={event => onChange(`${date}T${event.target.value}`)} /></div>
    </div>
  </fieldset>
}
