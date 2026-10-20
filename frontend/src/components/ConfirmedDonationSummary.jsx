import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../context/AuthContext'
import { liveQueryOptions } from '../services/liveQueries'
import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { api } from '../services/apiClient'
import styles from './ConfirmedDonationSummary.module.css'
import printStyles from './AnalyticsPrintReport.module.css'

function formatDate(value) {
  return new Date(`${value}T00:00:00Z`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' })
}

export default function ConfirmedDonationSummary({ version = 0 }) {
  const [from, setFrom] = useState(() => { const date=new Date(); date.setUTCDate(date.getUTCDate()-29); return date.toISOString().slice(0,10) })
  const [to, setTo] = useState(() => new Date().toISOString().slice(0,10))
  const [chapter, setChapter] = useState('')
  const [chapters, setChapters] = useState([])
  const { user } = useAuth()
  const params = new URLSearchParams({date_from:from,date_to:to,...(chapter ? {chapter_id:chapter} : {})}).toString()
  useEffect(() => { let active=true; api.get('/api/chapters').then(result => {if(active) setChapters(result.chapters || [])}).catch(()=>{}); return ()=>{active=false} }, [])
  const query = useQuery({ ...liveQueryOptions, queryKey: ['confirmed-donation-summary', user?.id, params, version], enabled: Boolean(user?.id && from && to && from <= to), queryFn: () => api.get('/api/analytics/confirmations?' + params) })
  const data = query.data
  const error = !from || !to || from > to ? 'Choose a valid confirmation date range.' : query.error?.message
  const loading = query.isPending && !error
  const ready = !loading && !error && data && data.date_from === from && data.date_to === to && String(data.chapter_id ?? '') === chapter
  const scopeLabel = data?.chapter_id == null ? 'All chapters' : chapters.find(row=>String(row.id)===String(data.chapter_id))?.name || `Chapter ${data.chapter_id}`
  const dailyTable = ready && (data.daily_confirmations.length === 0 ? <p className={styles.empty}>No confirmed donation reports in this period.</p> : <div className={`table-container ${styles.table}`}><table><thead><tr><th>Confirmation date (UTC)</th><th>Confirmed reports</th></tr></thead><tbody>{data.daily_confirmations.map(row=><tr key={row.confirmation_date_utc}><td><time dateTime={row.confirmation_date_utc}>{formatDate(row.confirmation_date_utc)}</time></td><td>{row.confirmed_reports}</td></tr>)}</tbody></table></div>)
  const printReport = ready ? <article className={printStyles.report} aria-label="Printable confirmed donation summary"><h1>BloodMatch confirmed donation summary</h1>
    <p>Confirmation dates: {data.date_from} through {data.date_to} (UTC). Request chapter scope: {scopeLabel}.</p>
    <p>{data.total_confirmed_reports} confirmed donation {data.total_confirmed_reports === 1 ? 'report' : 'reports'}. Counts use confirmation time; donation dates and donated units are not included.</p>
    {dailyTable}</article> : null
  return <>
    <section className={`card ${styles.summary}`} aria-labelledby="confirmed-summary-heading">
      <h2 id="confirmed-summary-heading">Confirmed donation summary</h2>
      <div className={styles.toolbar}>
        <div className={styles.filters} role="group" aria-label="Confirmation date range and request chapter">
          <div className="field"><label htmlFor="confirmed-from">From (UTC)</label><input type="date" id="confirmed-from" value={from} onChange={event=>setFrom(event.target.value)} /></div>
          <div className="field"><label htmlFor="confirmed-to">To (UTC)</label><input type="date" id="confirmed-to" value={to} onChange={event=>setTo(event.target.value)} /></div>
          <div className="field"><label htmlFor="confirmed-chapter">Request chapter</label><select id="confirmed-chapter" value={chapter} onChange={event=>setChapter(event.target.value)}><option value="">All chapters</option>{chapters.map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></div>
        </div>
        <div className={styles.actions}>
          {ready && <a className="btn btn-secondary" download href={`/api/analytics/confirmations/export?${params}`}>Download CSV</a>}
          <button type="button" className="btn btn-secondary" disabled={!ready} onClick={()=>window.print()}>Print / Save PDF</button>
        </div>
      </div>
      {loading && <p role="status">Loading confirmed-donation summary…</p>}
      {error && <div className="alert alert-error" role="alert">{error} <button className="btn btn-secondary" onClick={() => query.refetch()}>Retry</button></div>}
      {dailyTable}
    </section>
    {typeof document === 'undefined' ? printReport : printReport && createPortal(printReport,document.body)}
  </>
}
