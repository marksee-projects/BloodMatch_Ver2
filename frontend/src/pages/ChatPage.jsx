import { formatNotificationTime } from '../services/notifications'
import { useNavigate, useLocation } from 'react-router-dom'
import { useEffect, useRef, useState } from 'react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { useChat } from '../context/ChatContext'
import { useNotify } from '../context/NotificationContext'
import { Button } from '../components/ui/Button'
import styles from './ChatPage.module.css'

export default function ChatPage() {
  const { user } = useAuth()
  const { canChat, fetchUnreadCount } = useChat()
  const notify = useNotify()
  const navigate = useNavigate()
  const route = useLocation()
  const [conversations, setConversations] = useState([])
  const [requests, setRequests] = useState([])
  const [thread, setThread] = useState(null)
  const [messages, setMessages] = useState([])
  const [search, setSearch] = useState('')
  const [body, setBody] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [reporting, setReporting] = useState(false)
  const [reason, setReason] = useState('')
  const [selected, setSelected] = useState([])
  const inFlight = useRef(false)
  const listInFlight = useRef(false)
  const messageArea = useRef(null)
  const pollCursor = useRef(0)
  const readCursor = useRef(0)
  const [hasOlder, setHasOlder] = useState(true)
  const load = async () => {
    if (listInFlight.current || document.hidden) return
    listInFlight.current = true
    setError('')
    try {
      const [c, r] = await Promise.all([api.get('/api/chat/conversations'), api.get('/api/chat/requests')])
      setConversations(c.conversations || []); const requested = Number(new URLSearchParams(route.search).get('conversation')); if (requested) { const found = (c.conversations || []).find(row => Number(row.id) === requested); if (found) setThread(found); else setError('This conversation is unavailable.') } setRequests(r.requests || []);
    } catch (e) { setError(e.message) } finally { listInFlight.current = false }
  }
  useEffect(() => { if (!canChat) return undefined; load(); const timer = setInterval(load, 30000); return () => clearInterval(timer) }, [user?.id, canChat, route.search])
  useEffect(() => {
    setMessages([]); setSelected([]); setReporting(false); setBody(''); pollCursor.current = 0; readCursor.current = 0; setHasOlder(true)
    if (!thread || !canChat) return undefined
    let active = true
    const poll = async () => {
      if (document.hidden || inFlight.current) return
      inFlight.current = true
      try {
        const data = await api.get(`/api/chat/conversations/${thread.id}/messages?limit=100&after_id=${pollCursor.current}`)
        if (!active) return
        const rows = data.messages || []; const area = messageArea.current; const follow = !area || area.scrollHeight - area.scrollTop - area.clientHeight < 80; setMessages(current => [...new Map([...current, ...rows].map(m => [m.id, m])).values()].sort((a,b) => a.id-b.id))
        if (follow) window.requestAnimationFrame(() => { if (active && messageArea.current) messageArea.current.scrollTop = messageArea.current.scrollHeight })
        if (rows.length) pollCursor.current = Math.max(pollCursor.current, rows.at(-1).id)
        if (pollCursor.current > readCursor.current) { await api.post(`/api/chat/conversations/${thread.id}/read`, { last_message_id: pollCursor.current }); readCursor.current = pollCursor.current; await fetchUnreadCount() }
      } catch (e) { if (active) setError(e.message) }
      finally { inFlight.current = false }
    }
    poll(); const timer = setInterval(poll, 3000)
    return () => { active = false; clearInterval(timer) }
  }, [thread?.id, user?.id, canChat])
  const older = async () => {
    if (busy || !messages.length) return
    setBusy(true)
    try { const r = await api.get(`/api/chat/conversations/${thread.id}/messages?before_id=${messages[0].id}&limit=100`); const rows = r.messages || []; setHasOlder(rows.length === 100); setMessages(current => [...new Map([...rows, ...current].map(m => [m.id, m])).values()].sort((a,b) => a.id-b.id)) }
    catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { setBusy(false) }
  }
  const decide = async (id, action) => {
    if (busy) return
    setBusy(true)
    try { await api.post(`/api/chat/requests/${id}/decision`, { action }); await load(); notify({ severity: 'success', message: `Request ${action === 'accept' ? 'accepted' : action === 'decline' ? 'declined' : 'cancelled'}.` }) }
    catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { setBusy(false) }
  }
  const send = async (event) => {
    event.preventDefault(); if (busy || !body.trim()) return
    setBusy(true)
    try { const r = await api.post(`/api/chat/conversations/${thread.id}/messages`, { body }); setMessages(rows => [...rows.filter(m => m.id !== r.message.id), r.message]); setBody('') }
    catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { setBusy(false) }
  }
  const report = async (event) => {
    event.preventDefault(); if (busy || !selected.length) return
    setBusy(true)
    try { await api.post(`/api/chat/conversations/${thread.id}/reports`, { reason, message_ids: selected }); setReporting(false); setReason(''); setSelected([]); notify({ severity: 'success', message: 'Conversation report submitted.' }) }
    catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { setBusy(false) }
  }
  if (!canChat) return <p>Complete account verification before using chat.</p>
  return <section className={styles.page}>
    <h1>Chats</h1>
    {error && <div role="alert">{error}<Button onClick={load}>Retry</Button></div>}
    <div className={styles.layout}>
      <aside className={`${styles.list} ${thread ? styles.hideOnMobile : ''}`}>
        <label htmlFor="chat-search">Search chats</label><input id="chat-search" value={search} onChange={e => setSearch(e.target.value)} />
        <h2>Message requests</h2>
        {!requests.length && <p>No pending requests.</p>}
        {requests.map(r => <div className={styles.request} key={r.id}><strong>{Number(r.sender_id) === Number(user.id) ? r.recipient_name : r.sender_name}</strong>
          <div className={styles.actions}>{Number(r.recipient_id) === Number(user.id) ? <><Button disabled={busy} onClick={() => decide(r.id, 'accept')}>Accept</Button><Button variant="secondary" disabled={busy} onClick={() => decide(r.id, 'decline')}>Decline</Button></> : <Button variant="secondary" disabled={busy} onClick={() => decide(r.id, 'cancel')}>Cancel request</Button>}</div>
        </div>)}
        <h2>Conversations</h2>
        {!conversations.length && <p>No accepted conversations.</p>}
        {conversations.filter(c => c.recipient?.full_name?.toLowerCase().includes(search.toLowerCase())).map(c => <button className={styles.conversation} key={c.id} onClick={() => { setThread(c); navigate(`/chat?conversation=${c.id}`) }} aria-pressed={thread?.id === c.id}>{c.recipient?.full_name}{c.unread_count > 0 && ` (${c.unread_count} unread)`}</button>)}
      </aside>
      <div className={styles.thread}>
        {!thread ? <p>Select a conversation.</p> : <><div className={styles.actions}><h2>{thread.recipient?.full_name}</h2><Button variant="secondary" onClick={() => { setThread(null); navigate('/chat') }}>Back to chats</Button><Button variant="secondary" onClick={() => setReporting(!reporting)}>Report conversation</Button></div>
          <Button variant="secondary" disabled={busy || !messages.length || !hasOlder} onClick={older}>Load older messages</Button>
          <div ref={messageArea} className={styles.messages}>{messages.map(m => <div key={m.id} className={Number(m.sender_id) === Number(user.id) ? styles.own : styles.message}>
            {reporting && <input type="checkbox" aria-label={`Select message from ${m.sender_name}: ${m.body.slice(0, 120)}`} checked={selected.includes(m.id)} onChange={e => setSelected(ids => e.target.checked ? [...ids, m.id] : ids.filter(id => id !== m.id))} />}
            <strong>{m.sender_name}</strong><p>{m.body}</p><small>{formatNotificationTime(m.created_at)}</small>
          </div>)}</div>
          {reporting ? <form onSubmit={report}><label htmlFor="report-reason">Report reason</label><input id="report-reason" required maxLength={500} value={reason} onChange={e => setReason(e.target.value)} /><p>Choose up to 10 messages. Relevant context will be shared with the administrator.</p><Button type="submit" disabled={busy || !selected.length || selected.length > 10}>Submit report</Button></form> : <form className={styles.actions} onSubmit={send}><label className="sr-only" htmlFor="chat-message">Message</label><input id="chat-message" maxLength={2000} value={body} onChange={e => setBody(e.target.value)} /><Button type="submit" disabled={busy || !body.trim()}>Send</Button></form>}
        </>}
      </div>
    </div>
  </section>
}
