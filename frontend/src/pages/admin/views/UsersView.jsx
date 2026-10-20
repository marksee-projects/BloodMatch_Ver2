import { useActionNoticeState } from '../../../context/NotificationContext'
import { useEffect, useRef, useState } from 'react'
import { api } from '../../../services/apiClient'
import { useAuth } from '../../../context/AuthContext'
import { Button } from '../../../components/ui/Button'
import ConfirmationDialog from '../../../components/ConfirmationDialog'
import { LoadingSpinner } from '../../../components/ui/LoadingSpinner'
import styles from './UsersView.module.css'

export default function UsersView() {
  const { user } = useAuth()
  const [rows, setRows] = useState(null)
  const [chapters, setChapters] = useState([])
  const [search, setSearch] = useState('')
  const [filters, setFilters] = useState({ q: '', role_group: 'member', verification_status: '', account_status: '', donor_window: '', chapter_id: '' })
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [version, setVersion] = useState(0)
  const [error, setError] = useActionNoticeState(null, 'error')
  const [message, setMessage] = useActionNoticeState(null, 'success')
  const [edit, setEdit] = useState(null)
  const [busy, setBusy] = useState(false)
  const busyRef = useRef(false)
  useEffect(() => { api.get('/api/chapters').then(data => setChapters(data.chapters || [])).catch(err => setError(err.message)) }, [])
  useEffect(() => {
    const timer = setTimeout(() => {
      setFilters(current => current.q === search.trim() ? current : { ...current, q: search.trim() })
      setPage(1)
    }, 300)
    return () => clearTimeout(timer)
  }, [search])
  useEffect(() => {
    let active = true
    setRows(null)
    setError(null)
    const params = new URLSearchParams({ ...filters, page: String(page), page_size: '25' })
    api.get(`/api/admin/users?${params}`).then(data => {
      if (active) { setRows(data.users || []); setTotal(data.total || 0) }
    }).catch(err => { if (active) setError(err.message) })
    return () => { active = false }
  }, [filters, page, version, user?.id])
  const choose = field => event => { setFilters(current => ({ ...current, [field]: event.target.value, ...(field === 'role_group' ? { verification_status: '' } : {}) })); setPage(1) }
  const save = async () => {
    if (!edit || busyRef.current || String(edit.target.id) === String(user?.id)) return
    busyRef.current = true; setBusy(true); setError(null)
    try {
      const body = edit.action === 'role' ? { role: edit.role, chapter_id: edit.chapter_id ? Number(edit.chapter_id) : null }
        : edit.action === 'chapter' ? { chapter_id: edit.chapter_id ? Number(edit.chapter_id) : null } : {}
      await api.post(`/api/admin/users/${edit.target.id}/${edit.action}`, body)
      setMessage(`${edit.target.full_name}'s account was updated.`); setEdit(null); setVersion(value => value + 1)
    } catch (err) { setError(err.message); setVersion(value => value + 1) }
    finally { busyRef.current = false; setBusy(false) }
  }
  return <div className="container">
    <h1 className="sr-only">User management</h1>

    {error && !edit && <div className="alert alert-error" role="alert">{error}<Button variant="secondary" onClick={() => { setError(null); setVersion(value => value + 1) }}>Retry</Button></div>}
    <section className={`card ${styles.managementCard}`} aria-label="User accounts">
    <form className={styles.filters} onSubmit={event => event.preventDefault()}>
      <div className="field"><label htmlFor="admin-user-search">Name or email</label><input id="admin-user-search" type="search" value={search} onChange={event => setSearch(event.target.value)} /></div>
      <div className="field"><label htmlFor="admin-user-group">Accounts</label><select id="admin-user-group" value={filters.role_group} onChange={choose('role_group')}><option value="member">Members</option><option value="staff">Administrator</option></select></div>
      <div className="field"><label htmlFor="admin-user-verification">Verification status</label><select id="admin-user-verification" value={filters.verification_status} onChange={choose('verification_status')}><option value="">All verification statuses</option>{['pending','verified','rejected'].map(status => <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>)}</select></div>
      <div className="field"><label htmlFor="admin-user-status">Account status</label><select id="admin-user-status" value={filters.account_status} onChange={choose('account_status')}><option value="">All statuses</option><option value="active">Active</option><option value="deactivated">Deactivated</option></select></div>
      <div className="field"><label htmlFor="admin-donor-window">Donation status</label><select id="admin-donor-window" value={filters.donor_window} onChange={choose('donor_window')}><option value="">All donation statuses</option><option value="standby">Standby</option><option value="cooldown">Cooldown</option></select></div>
      <div className="field"><label htmlFor="admin-user-chapter">Chapter</label><select id="admin-user-chapter" value={filters.chapter_id} onChange={choose('chapter_id')}><option value="">All chapters</option>{chapters.map(chapter => <option key={chapter.id} value={chapter.id}>{chapter.name}</option>)}</select></div>
    </form>
    {!rows ? <LoadingSpinner text="Loading users…" /> : <>
      {rows.length === 0 ? <p>No accounts match your filters.</p> : <div className="table-container"><table className={styles.accountsTable}>
        <thead><tr><th>Name</th><th>Verification status</th><th>Access role</th><th>Chapter</th><th>Account status</th><th>Actions</th></tr></thead>
        <tbody>{rows.map(target => <tr key={target.id}>
        <td><strong>{target.full_name}</strong><div className={styles.email}>{target.email}</div></td>
        <td>{target.verification_status ? target.verification_status.charAt(0).toUpperCase() + target.verification_status.slice(1) : 'Not provided'}</td>
        <td>{target.role === 'admin' ? 'Admin/Officer' : 'Member'}</td><td>{chapters.find(chapter => String(chapter.id) === String(target.chapter_id))?.name || 'Not assigned'}</td><td>{target.account_status}{target.donor_window?.blocked && <div>{target.donor_window.which} until {new Date(target.donor_window.eligible_again_at_utc.replace(' ', 'T') + 'Z').toLocaleString()}</div>}</td>
        <td><div className={styles.actions}>{['chapter',target.account_status === 'active' ? 'deactivate' : 'reactivate'].map(action => <Button key={action} variant="secondary" size="sm" disabled={busy || String(target.id) === String(user?.id)}
          onClick={() => { setError(null); setEdit({ target, action, role: target.role, chapter_id: target.chapter_id == null ? '' : String(target.chapter_id) }) }}>
          {action === 'role' ? 'Change role' : action === 'chapter' ? 'Change chapter' : action === 'deactivate' ? 'Deactivate' : 'Reactivate'}</Button>)}</div>
        </td></tr>)}</tbody></table></div>}
      <nav className={styles.pagination} aria-label="User account pages"><Button variant="secondary" disabled={page === 1 || busy} onClick={() => setPage(value => value - 1)}>Previous</Button><span>Page {page}</span><Button variant="secondary" disabled={page * 25 >= total || busy} onClick={() => setPage(value => value + 1)}>Next</Button></nav>
    </>}
    </section>
    <ConfirmationDialog open={Boolean(edit)} title="Review account change" confirmLabel="Apply change" busy={busy} error={error}
      onConfirm={save} onCancel={() => { setEdit(null); setError(null) }}>
      {edit && <><p>{edit.target.full_name} ({edit.target.email})</p><p>Current: {edit.target.role === 'admin' ? 'Admin/Officer' : 'Member'}, {edit.target.account_status}, {chapters.find(chapter => String(chapter.id) === String(edit.target.chapter_id))?.name || 'Chapter not assigned'}.</p>
        {edit.action === 'role' && <div className="field"><label htmlFor="edit-user-role">New access role</label><select id="edit-user-role" value={edit.role} disabled={busy} onChange={event => setEdit(current => ({ ...current, role: event.target.value }))}><option value="member">Member</option><option value="admin">Admin/Officer — global staff access</option></select>{edit.role === 'admin' && edit.target.role !== 'admin' && <p>Grants staff access across all chapters, including member-document review and user management. Membership verification status is unchanged.</p>}</div>}
        {['role','chapter'].includes(edit.action) ? <div className="field"><label htmlFor="edit-user-chapter">Chapter assignment</label><select id="edit-user-chapter" value={edit.chapter_id} disabled={busy} onChange={event => setEdit(current => ({ ...current, chapter_id: event.target.value }))}><option value="" disabled={edit.action === 'role' && edit.target.chapter_id != null}>Not assigned</option>{chapters.map(chapter => <option key={chapter.id} value={chapter.id}>{chapter.name}</option>)}</select>{edit.action === 'role' && <small>Use Change chapter separately to clear an assignment where permitted.</small>}</div>
          : <p>This will {edit.action} this account.</p>}</>}
    </ConfirmationDialog>
  </div>
}
