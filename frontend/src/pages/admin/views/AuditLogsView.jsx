import { useEffect, useState } from 'react'
import { ArrowLeft, ArrowRight, X } from '@phosphor-icons/react'
import { api } from '../../../services/apiClient'
import AuditFilterToolbar from '../../../components/AuditFilterToolbar'

export default function AuditLogsView() {
  const [data, setData] = useState(null)
  const [chapters,setChapters] = useState([])
  const [filters,setFilters] = useState({})
  useEffect(() => { let active=true; api.get('/api/chapters').then(result => { if(active) setChapters(result.chapters || []) }).catch(err => { if(active) setError(err.message) }); return () => { active=false } }, [])
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(25)
  const [action, setAction] = useState('')
  const [actorId, setActorId] = useState('')
  const [targetType, setTargetType] = useState('')
  const [targetId, setTargetId] = useState('')
  const [chapterId, setChapterId] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)
  const [selectedContext, setSelectedContext] = useState(null)

  useEffect(() => {
    let active = true
    setLoading(true); setError(null)
    const params = new URLSearchParams({page:String(page),page_size:String(pageSize)})
    Object.entries(filters).forEach(([key,value]) => { if(String(value).trim()) params.set(key,String(value).trim()) })
    api.get(`/api/admin/audit-logs?${params}`).then(result => { if(active) setData(result) })
      .catch(err => { if(active) setError(err.message) }).finally(() => { if(active) setLoading(false) })
    return () => { active=false }
  }, [page,pageSize,filters])

  const handleSearch = (e) => {
    e.preventDefault()
    setPage(1)
    setFilters({action,actor_id:actorId,target_type:targetType,target_id:targetId,chapter_id:chapterId,date_from:dateFrom,date_to:dateTo})
  }

  const handleReset = () => {
    setAction('')
    setActorId('')
    setTargetType('')
    setTargetId('')
    setChapterId('')
    setDateFrom('')
    setDateTo('')
    setFilters({})
    setPage(1)
  }

  return (
    <div className="container wide" style={{ padding: 'var(--space-6) 0' }}>

      {error && <div className="alert alert-error" role="alert">{error}</div>}

      {/* Filter Toolbar */}
      <AuditFilterToolbar draft={{action,actorId,targetType,targetId,chapterId,dateFrom,dateTo}} chapters={chapters}
        change={(field,value) => ({action:setAction,actorId:setActorId,targetType:setTargetType,targetId:setTargetId,chapterId:setChapterId,dateFrom:setDateFrom,dateTo:setDateTo})[field](value)}
        onSubmit={handleSearch} onReset={handleReset} pageSize={pageSize} onPageSize={value => { setPageSize(value); setPage(1) }} />

      {/* Audit Event Table */}
      {loading ? (
        <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
          <p className="muted">Loading system audit records…</p>
        </div>
      ) : !data || data.logs?.length === 0 ? (
        <div className="empty-state">
          <h3>No Audit Records Found</h3>
          <p>No audit events match your selected search criteria.</p>
        </div>
      ) : (
        <section className="card" style={{ padding: 0, overflow: 'hidden' }}>
          <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Timestamp (UTC)</th>
                  <th>Action</th>
                  <th>Actor</th>
                  <th>Target Type & ID</th>
                  <th>Context Data</th>
                </tr>
              </thead>
              <tbody>
                {data.logs.map((log) => (
                  <tr key={log.id}>
                    <td><code>#{log.id}</code></td>
                    <td><code>{log.created_at}</code></td>
                    <td><strong>{log.action}</strong></td>
                    <td>{log.actor_name || (log.actor_id ? `User #${log.actor_id}` : 'System / Unauth')}</td>
                    <td>{log.target_type ? `${log.target_type} #${log.target_id}` : '–'}</td>
                    <td>
                      {log.context ? (
                        <button
                          type="button"
                          className="btn btn-secondary btn-sm"
                          onClick={() => setSelectedContext(log)}
                        >
                          Inspect Context
                        </button>
                      ) : (
                        <span className="muted">–</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Pagination Controls */}
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: 'var(--space-3) var(--space-4)', borderTop: '1px solid var(--color-border)' }}>
            <span className="muted" style={{ fontSize: '0.8125rem' }}>
              Showing {data.logs.length} of {data.total} event(s) · Page {data.page} of {data.total_pages || 1}
            </span>

            <div className="button-group">
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                disabled={data.page <= 1}
                onClick={() => setPage((p) => p - 1)}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
              >
                <ArrowLeft size={14} weight="regular" aria-hidden="true" /> Prev
              </button>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                disabled={data.page >= (data.total_pages || 1)}
                onClick={() => setPage((p) => p + 1)}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
              >
                Next <ArrowRight size={14} weight="regular" aria-hidden="true" />
              </button>
            </div>
          </div>
        </section>
      )}

      {/* JSON Context Inspector Modal */}
      {selectedContext && (
        <div className="modal-backdrop" role="dialog" aria-labelledby="admin-context-title" aria-modal="true">
          <div className="modal-content" style={{ maxWidth: '640px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--space-3)' }}>
              <h3 id="admin-context-title" style={{ margin: 0 }}>
                Audit Event #{selectedContext.id} Context
              </h3>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => setSelectedContext(null)}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
              >
                <X size={14} weight="regular" aria-hidden="true" /> Close
              </button>
            </div>

            <p className="muted" style={{ fontSize: '0.8125rem', marginBottom: 'var(--space-3)' }}>
              Action: <strong>{selectedContext.action}</strong> · Timestamp: <code>{selectedContext.created_at}</code>
            </p>

            <pre style={{ padding: 'var(--space-4)', background: 'var(--color-surface-sunken)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-md)', overflowX: 'auto', maxHeight: '350px' }}>
              {JSON.stringify(selectedContext.context, null, 2)}
            </pre>
          </div>
        </div>
      )}
    </div>
  )
}
