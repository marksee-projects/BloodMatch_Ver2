import { createPortal } from 'react-dom'
import styles from './AnalyticsPrintReport.module.css'

const sections = [
  ['request_volume','Requests',['total_requests','open_requests','fulfilled_requests','cancelled_requests','expired_requests','resolved_requests','total_units_requested','fulfillment_rate_percent','cancellation_rate_percent','expiration_rate_percent']],
  ['verification_activity','Membership decisions',['approved','rejected']],
  ['donation_engagement','Donation reports',['pending_reports','confirmed_donations','rejected_reports','completed_matches']],
  ['donor_pool','Current donor pool',['total_enrolled','available','standby','cooldown','unavailable']],
]
const label = value => value.replaceAll('_',' ').replace(/\b\w/g, character => character.toUpperCase())
const numeric = value => typeof value === 'number' || (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value)))

export default function AnalyticsPrintReport({ data, scopeLabel }) {
  if (!data) return null
  const report = <article className={styles.report} aria-label="Printable aggregate analytics report">
    <header><h1>BloodMatch aggregate analytics</h1><p>Reporting dates: {data.date_from} through {data.date_to}<br />Scope: {scopeLabel}</p></header>
    <p>Requests are grouped by creation date; outcomes reflect their current status. Rates use resolved requests (fulfilled, cancelled and expired) as the denominator. Donor-pool figures are a current snapshot, not historical availability.</p>
    {sections.map(([key,title,metrics]) => <section key={key}><h2>{title}</h2><table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>{metrics.filter(metric => numeric(data[key]?.[metric])).map(metric => <tr key={metric}><th scope="row">{label(metric)}</th><td>{data[key][metric]}{metric.endsWith('_percent') ? '%' : ''}</td></tr>)}</tbody></table></section>)}
    {['demand_by_blood_type','demand_by_urgency'].map(key => <section key={key}><h2>{key === 'demand_by_blood_type' ? 'Demand by blood group' : 'Demand by urgency'}</h2><table><thead><tr><th>Category</th><th>Requests</th><th>Units requested</th></tr></thead><tbody>{(key === 'demand_by_blood_type' ? ['A+','A-','B+','B-','AB+','AB-','O+','O-'] : ['routine','urgent','emergency']).map(category => <tr key={category}><th scope="row">{category}</th><td>{numeric(data[key]?.[category]?.requests) ? data[key][category].requests : 0}</td><td>{numeric(data[key]?.[category]?.units) ? data[key][category].units : 0}</td></tr>)}</tbody></table></section>)}
    <section><h2>Daily request records</h2><table><thead><tr><th>Date</th><th>Created</th><th>Units</th><th>Fulfilled</th><th>Cancelled</th><th>Expired</th></tr></thead><tbody>{(data.daily_request_trend || []).map(row => <tr key={row.date}><td>{row.date}</td>{['requests_created','units_requested','fulfilled','cancelled','expired'].map(metric => <td key={metric}>{numeric(row[metric]) ? row[metric] : 0}</td>)}</tr>)}</tbody></table></section>
  </article>
  return typeof document === 'undefined' ? report : createPortal(report,document.body)
}
