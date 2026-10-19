import { useId, useRef } from 'react'
import { CalendarBlank, Clock } from '@phosphor-icons/react'
import styles from './DonationDateTimeFields.module.css'

export default function DonationDateTimeFields({ value = '', onChange, legend = 'Actual donation date and time', required = true }) {
  const id = useId()
  const dateInput = useRef(null)
  const timeInput = useRef(null)
  const openPicker = ref => { try { if (typeof ref.current?.showPicker === 'function') ref.current.showPicker(); else ref.current?.focus() } catch { ref.current?.focus() } }
  const [date = '', time = ''] = value.split('T')
  return <fieldset className={styles.fields}>
    <legend className={styles.visuallyHidden}>{legend}</legend>
    <div className={styles.grid}>
      <div><label htmlFor={`${id}-date`}>Donation date</label><div className={styles.picker}><input ref={dateInput} id={`${id}-date`} type="date" required={required} value={date} onChange={event => onChange(`${event.target.value}T${time}`)} /><button type="button" aria-label="Choose donation date" onClick={() => openPicker(dateInput)}><CalendarBlank size={20} aria-hidden="true" /></button></div></div>
      <div><label htmlFor={`${id}-time`}>Donation time</label><div className={styles.picker}><input ref={timeInput} id={`${id}-time`} type="time" step="60" required={required} value={time.slice(0, 5)} onChange={event => onChange(`${date}T${event.target.value}`)} /><button type="button" aria-label="Choose donation time" onClick={() => openPicker(timeInput)}><Clock size={20} aria-hidden="true" /></button></div></div>
    </div>
  </fieldset>
}
