import styles from './AuditFilterToolbar.module.css'

const categories = [['', 'All events'], ['auth.*', 'Sign-in and account access'], ['authz.*', 'Permission checks'], ['request.*', 'Requests'], ['verification.*', 'Membership verification'], ['donation.*', 'Donations'], ['match.*', 'Donor offers'], ['document.*', 'Documents'], ['admin.*', 'Staff management'], ['profile.*', 'Profiles']]

export default function AuditFilterToolbar({ draft, change, chapters, onSubmit, onReset, pageSize, onPageSize }) {
  const advancedCount = ['actorId','targetType','targetId'].filter(key => draft[key]).length + (draft.action && !categories.some(([value]) => value === draft.action) ? 1 : 0)
  const field = (key, label, type = 'text') => <div className="field"><label htmlFor={`audit-${key}`}>{label}</label><input id={`audit-${key}`} type={type} value={draft[key]} onChange={event => change(key,event.target.value)} /></div>
  return <form className="card" onSubmit={onSubmit}>
    <div className={styles.grid}>
      {field('dateFrom','From','date')}{field('dateTo','Through','date')}
      <div className="field"><label htmlFor="audit-category">Event category</label><select id="audit-category" value={draft.action} onChange={event => change('action',event.target.value)}>{categories.map(([value,label]) => <option key={value} value={value}>{label}</option>)}{draft.action && !categories.some(([value]) => value === draft.action) && <option value={draft.action}>Custom action: {draft.action}</option>}</select></div>
      <div className="field"><label htmlFor="audit-chapter">Chapter</label><select id="audit-chapter" value={draft.chapterId} onChange={event => change('chapterId',event.target.value)}><option value="">All chapters</option>{chapters.map(chapter => <option key={chapter.id} value={chapter.id}>{chapter.name}</option>)}</select></div>
    </div>
    <details className={styles.advanced}><summary>Advanced filters{advancedCount > 0 ? ` (${advancedCount} active)` : ''}</summary>
      <p>Actor is the account that performed the action. Target is the affected record. An exact action code or a wildcard such as request.* can narrow the results.</p>
      <div className={styles.grid}>{field('action','Action code')}{field('actorId','Actor account ID')}
        <div className="field"><label htmlFor="audit-target-type">Affected record type</label><select id="audit-target-type" value={draft.targetType} onChange={event => change('targetType',event.target.value)}><option value="">All types</option>{['user','blood_request','member_document','donation_report','chapter'].map(type => <option key={type} value={type}>{type.replaceAll('_',' ')}</option>)}</select></div>
        {field('targetId','Affected record ID')}
      </div>
    </details>
    <div className={styles.actions}><div className="button-group"><button className="btn btn-sm" type="submit">Apply filters</button><button className="btn btn-secondary btn-sm" type="button" onClick={onReset}>Reset</button></div>
      <div className="field"><label htmlFor="audit-page-size">Rows per page</label><select id="audit-page-size" value={pageSize} onChange={event => onPageSize(Number(event.target.value))}>{[10,25,50].map(size => <option key={size}>{size}</option>)}</select></div>
    </div>
  </form>
}
