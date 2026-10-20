import { useId, useState } from 'react'
import { CaretDown } from '@phosphor-icons/react'
import { Button } from './ui/Button'
import styles from './DonationReportInbox.module.css'

export default function ReportDisclosure({ label, hideLabel, children }) {
  const id = useId()
  const [open, setOpen] = useState(false)
  return <section className={styles.disclosure}>
    <Button type="button" variant="secondary" aria-expanded={open} aria-controls={id} onClick={() => setOpen(!open)}>
      {open ? hideLabel : label}<CaretDown size={16} aria-hidden="true" className={open ? styles.caretOpen : ''} />
    </Button>
    {open && <div id={id} className={styles.disclosureBody}>{children}</div>}
  </section>
}
