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
  const [filters, setFilters] = useState({ q: '', role: '', account_status: '', chapter_id: '' })
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [version, setVersion] = useState(0)
  const [error, setError] = useState(null)
  const [message, setMessage] = useState(null)
  const [edit, setEdit] = useState(null)
  const [busy, setBusy] = useState(false)
  const busyRef = useRef(false)
  useEffect(() => { api.get('/api/chapters').then(data => setChapters(data.chapters || [])).catch(err => setError(err.message)) }, [])
  useEffect(() => {
    let active = true
    setRows(null)
    const params = new URLSearchParams({ ...filters, page: String(page), page_size: '25' })
    api.get(`/api/admin/users?${params}`).then(data => {
      if (active) { setRows(data.users || []); setTotal(data.total || 0) }
    }).catch(err => { if (active) setError(err.message) })
    return () => { active = false }
  }, [filters, page, version, user?.id])
  const choose = field => event => { setFilters(current => ({ ...current, [field]: event.target.value })); setPage(1) }
  const save = async () => {
    if (!edit || busyRef.current || String(edit.target.id) === String(user?.id)) return
    if (edit.action === 'role' && edit.role === 'officer' && !edit.chapter_id) { setError('An officer requires a chapter.'); return }
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
    <h1>User management</h1>
    {message && <p className="alert alert-success" role="status">{message}</p>}
    {error && !edit && <div className="alert alert-error" role="alert">{error}<Button variant="secondary" onClick={() => { setError(null); setVersion(value => value + 1) }}>Retry</Button></div>}
    <form className={styles.filters} onSubmit={event => { event.preventDefault(); setFilters(current => ({ ...current, q: search.trim() })); setPage(1) }}>
      <div className="field"><label htmlFor="admin-user-search">Name or email</label><input id="admin-user-search" type="search" value={search} onChange={event => setSearch(event.target.value)} /></div>
      <div className="field"><label htmlFor="admin-user-role">Role</label><select id="admin-user-role" value={filters.role} onChange={choose('role')}><option value="">All roles</option>{['member','officer','admin'].map(role => <option key={role}>{role}</option>)}</select></div>
      <div className="field"><label htmlFor="admin-user-status">Account status</label><select id="admin-user-status" value={filters.account_status} onChange={choose('account_status')}><option value="">All statuses</option><option value="active">Active</option><option value="deactivated">Deactivated</option></select></div>
      <div className="field"><label htmlFor="admin-user-chapter">Chapter</label><select id="admin-user-chapter" value={filters.chapter_id} onChange={choose('chapter_id')}><option value="">All chapters</option>{chapters.map(chapter => <option key={chapter.id} value={chapter.id}>{chapter.name}</option>)}</select></div>
      <Button type="submit" variant="secondary">Search</Button>
    </form>
    {!rows ? <LoadingSpinner text="Loading users…" /> : <>
      <p role="status">{total} accounts</p>
      {rows.length === 0 ? <p>No accounts match your filters.</p> : <div className={styles.list}>{rows.map(target => <article className="card" key={target.id}>
        <h2>{target.full_name}</h2><p>{target.email}</p><p>{target.role} · {chapters.find(chapter => String(chapter.id) === String(target.chapter_id))?.name || 'Chapter not assigned'} · {target.account_status}</p>
        <div className={styles.actions}>{['role','chapter',target.account_status === 'active' ? 'deactivate' : 'reactivate'].map(action => <Button key={action} variant="secondary" disabled={busy || String(target.id) === String(user?.id)}
          onClick={() => { setError(null); setEdit({ target, action, role: target.role, chapter_id: target.chapter_id == null ? '' : String(target.chapter_id) }) }}>
          {action === 'role' ? 'Change role' : action === 'chapter' ? 'Change chapter' : action === 'deactivate' ? 'Deactivate' : 'Reactivate'}</Button>)}</div>
      </article>)}</div>}
      <div className={styles.actions}><Button variant="secondary" disabled={page === 1 || busy} onClick={() => setPage(value => value - 1)}>Previous</Button><span>Page {page}</span><Button variant="secondary" disabled={page * 25 >= total || busy} onClick={() => setPage(value => value + 1)}>Next</Button></div>
    </>}
    <ConfirmationDialog open={Boolean(edit)} title="Review account change" confirmLabel="Apply change" busy={busy} error={error}
      onConfirm={save} onCancel={() => { setEdit(null); setError(null) }}>
      {edit && <><p>{edit.target.full_name} ({edit.target.email})</p><p>Current: {edit.target.role}, {edit.target.account_status}, {chapters.find(chapter => String(chapter.id) === String(edit.target.chapter_id))?.name || 'Chapter not assigned'}.</p>
        {edit.action === 'role' && <div className="field"><label htmlFor="edit-user-role">New role</label><select id="edit-user-role" value={edit.role} disabled={busy} onChange={event => setEdit(current => ({ ...current, role: event.target.value }))}>{['member','officer','admin'].map(role => <option key={role}>{role}</option>)}</select></div>}
        {['role','chapter'].includes(edit.action) ? <div className="field"><label htmlFor="edit-user-chapter">Chapter assignment</label><select id="edit-user-chapter" value={edit.chapter_id} disabled={busy} onChange={event => setEdit(current => ({ ...current, chapter_id: event.target.value }))}><option value="" disabled={edit.action === 'role' && edit.target.chapter_id != null}>Not assigned</option>{chapters.map(chapter => <option key={chapter.id} value={chapter.id}>{chapter.name}</option>)}</select>{edit.action === 'role' && <small>Use Change chapter separately to clear an assignment where permitted.</small>}</div>
          : <p>This will {edit.action} this account.</p>}</>}
    </ConfirmationDialog>
  </div>
}
