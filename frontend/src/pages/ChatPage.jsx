import { formatNotificationTime } from '../services/notifications'
import { useNavigate, useLocation } from 'react-router-dom'
import { useEffect, useRef, useState } from 'react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { useChat } from '../context/ChatContext'
import { useNotify } from '../context/NotificationContext'
import { Button } from '../components/ui/Button'
import { ChatCircleDots, MagnifyingGlass, User } from '@phosphor-icons/react'
import styles from './ChatPage.module.css'
import ChatComposerInput from '../components/chat/ChatComposerInput'
import DonationContextCard from '../components/DonationContextCard'

export default function ChatPage() {
  const { user } = useAuth()
  const { canChat } = useChat()
  const route = useLocation()
  // A new account or route gets fresh state and cursors. Outstanding requests
  // from the previous workspace must never populate the next conversation.
  return <ChatWorkspace key={`${user?.id ?? 'guest'}:${canChat}:${route.search}`} />
}

function ChatWorkspace() {
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
  const [isOnline, setIsOnline] = useState(false)
  const [suggestions, setSuggestions] = useState(null)
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
  const mounted = useRef(false)
  useEffect(() => {
    mounted.current = true
    return () => { mounted.current = false }
  }, [])
  const load = async () => {
    if (listInFlight.current || document.hidden) return
    listInFlight.current = true
    setError('')
    try {
      const [c, r] = await Promise.all([api.get('/api/chat/conversations'), api.get('/api/chat/requests')])
      if (!mounted.current) return
      setConversations(c.conversations || []); const requested = Number(new URLSearchParams(route.search).get('conversation')); if (requested) { const found = (c.conversations || []).find(row => Number(row.id) === requested); setThread(found || null); if (!found) setError('This conversation is unavailable.') } setRequests(r.requests || []);
    } catch (e) { if (mounted.current) setError(e.message) } finally { listInFlight.current = false }
  }
  useEffect(() => { if (!canChat) return undefined; load(); const timer = setInterval(load, 30000); return () => clearInterval(timer) }, [user?.id, canChat, route.search])
  useEffect(() => {
    setIsOnline(false); setSuggestions(null); setMessages([]); setSelected([]); setReporting(false); setBody(''); pollCursor.current = 0; readCursor.current = 0; setHasOlder(true)
    if (!thread || !canChat) return undefined
    let active = true
    const poll = async () => {
      if (document.hidden || inFlight.current) return
      inFlight.current = true
      try {
        const data = await api.get(`/api/chat/conversations/${thread.id}/messages?limit=100${pollCursor.current ? '&after_id=' + pollCursor.current : ''}`)
        if (!active) return
        setIsOnline(Boolean(data.is_online)); setSuggestions(data.suggested_chats || null)
        const rows = data.messages || []; if (!pollCursor.current) setHasOlder(rows.length === 100); const area = messageArea.current; const follow = !area || area.scrollHeight - area.scrollTop - area.clientHeight < 80; setMessages(current => [...new Map([...current, ...rows].map(m => [m.id, m])).values()].sort((a,b) => a.id-b.id))
        if (follow) window.requestAnimationFrame(() => { if (active && messageArea.current) messageArea.current.scrollTop = messageArea.current.scrollHeight })
        if (rows.length) pollCursor.current = Math.max(pollCursor.current, rows.at(-1).id)
        if (!document.hidden && pollCursor.current > readCursor.current) { const fetchedId = pollCursor.current; await api.post(`/api/chat/conversations/${thread.id}/read`, { last_message_id: fetchedId }); if (!active) return; readCursor.current = fetchedId; await fetchUnreadCount() }
      } catch (e) { if (active) setError(e.message) }
      finally { inFlight.current = false }
    }
    poll(); const timer = setInterval(poll, 3000)
    return () => { active = false; clearInterval(timer) }
  }, [thread?.id, user?.id, canChat])
  const older = async () => {
    if (busy || !messages.length) return
    setBusy(true)
    const area = messageArea.current
    const previousHeight = area?.scrollHeight || 0
    const previousTop = area?.scrollTop || 0
    try { const r = await api.get(`/api/chat/conversations/${thread.id}/messages?before_id=${messages[0].id}&limit=100`); if (!mounted.current) return; const rows = r.messages || []; setHasOlder(rows.length === 100); setMessages(current => [...new Map([...rows, ...current].map(m => [m.id, m])).values()].sort((a,b) => a.id-b.id)); window.requestAnimationFrame(() => { if (mounted.current && area === messageArea.current) area.scrollTop = previousTop + area.scrollHeight - previousHeight }) }
    catch (e) { if (mounted.current) notify({ severity: 'error', message: e.message }) }
    finally { if (mounted.current) setBusy(false) }
  }
  const decide = async (id, action) => {
    if (busy) return
    setBusy(true)
    try { await api.post(`/api/chat/requests/${id}/decision`, { action }); if (!mounted.current) return; await load(); if (mounted.current) notify({ severity: 'success', message: `Request ${action === 'accept' ? 'accepted' : action === 'decline' ? 'declined' : 'cancelled'}.` }) }
    catch (e) { if (mounted.current) notify({ severity: 'error', message: e.message }) }
    finally { if (mounted.current) setBusy(false) }
  }
  const send = async (event) => {
    event.preventDefault(); if (busy || !body.trim()) return
    setBusy(true)
    try { const r = await api.post(`/api/chat/conversations/${thread.id}/messages`, { body, expected_sender_id: Number(user.id) }); if (!mounted.current) return; setMessages(rows => [...rows.filter(m => m.id !== r.message.id), r.message].sort((a,b) => a.id-b.id)); setBody(''); setSuggestions(null) }
    catch (e) { if (mounted.current) notify({ severity: 'error', message: e.message }) }
    finally { if (mounted.current) setBusy(false) }
  }
  const report = async (event) => {
    event.preventDefault(); if (busy || !selected.length) return
    setBusy(true)
    try { await api.post(`/api/chat/conversations/${thread.id}/reports`, { reason, message_ids: selected }); if (!mounted.current) return; setReporting(false); setReason(''); setSelected([]); notify({ severity: 'success', message: 'Conversation report submitted.' }) }
    catch (e) { if (mounted.current) notify({ severity: 'error', message: e.message }) }
    finally { if (mounted.current) setBusy(false) }
  }
  if (!canChat) return <section className={styles.page}><h1>Chats</h1><p className={styles.empty}>Complete account verification before using chat.</p></section>
  const filtered = conversations.filter(c => c.recipient?.full_name?.toLowerCase().includes(search.trim().toLowerCase()))
  return <section className={styles.page}>
    <h1 className="sr-only">Chats</h1>
    {error && <div className={styles.error} role="alert"><span>{error}</span><Button variant="secondary" onClick={load}>Retry</Button></div>}
    <div className={styles.layout}>
      <aside className={styles.list + (thread ? ' ' + styles.hideOnMobile : '')}>
        <div className={styles.search}><label className="sr-only" htmlFor="chat-search">Search chats</label><MagnifyingGlass size={18} aria-hidden="true" /><input className="chat-control" id="chat-search" placeholder="Search chats..." value={search} onChange={e => setSearch(e.target.value)} /></div>
        <div className={styles.sidebarScroll}>
          <section className={styles.listSection}><h2>Message requests <span>{requests.length}</span></h2>
            {!requests.length && <p className={styles.hint}>No pending requests.</p>}
            {requests.map(r => <div className={styles.request} key={r.id}><strong>{Number(r.sender_id) === Number(user.id) ? r.recipient_name : r.sender_name}</strong><small>{Number(r.recipient_id) === Number(user.id) ? 'Wants to chat with you' : 'Waiting for acceptance'}</small>
              <div className={styles.actions}>{Number(r.recipient_id) === Number(user.id) ? <><Button disabled={busy} onClick={() => decide(r.id, 'accept')}>Accept</Button><Button variant="secondary" disabled={busy} onClick={() => decide(r.id, 'decline')}>Decline</Button></> : <Button variant="secondary" disabled={busy} onClick={() => decide(r.id, 'cancel')}>Cancel request</Button>}</div>
            </div>)}
          </section>
          <section className={styles.listSection}><h2>Conversations</h2>
            {!filtered.length && <p className={styles.hint}>{search ? 'No chats match your search.' : 'No accepted conversations.'}</p>}
            {filtered.map(c => <button type="button" className={styles.conversation} key={c.id} onClick={() => navigate('/chat?conversation=' + c.id)} aria-pressed={Number(thread?.id) === Number(c.id)}><span className={styles.avatar}><User size={22} aria-hidden="true" /></span><span className={styles.identity}><strong>{c.recipient?.full_name} {c.recipient?.is_online && <span className={styles.onlineDot} role="img" aria-label="Online" />}</strong><small>{c.last_message?.body || 'Start the conversation'}</small></span>{c.unread_count > 0 && <span className={styles.unread} aria-label={c.unread_count + ' unread'}>{c.unread_count > 99 ? '99+' : c.unread_count}</span>}</button>)}
          </section>
        </div>
      </aside>
      <div className={styles.thread + (!thread ? ' ' + styles.emptyThread : '')}>
        {!thread ? <div className={styles.empty}><ChatCircleDots size={40} aria-hidden="true" /><h2>Select a conversation</h2></div> : <>
          <header className={styles.threadHeader}><div className={styles.person}><span className={styles.avatar}><User size={22} aria-hidden="true" /></span><div><h2>{thread.recipient?.full_name}</h2>{isOnline && <small><span className={styles.onlineDot} aria-hidden="true" /> Online</small>}</div></div><div className={styles.actions}><Button variant="secondary" onClick={() => navigate('/chat')}>Back to chats</Button><Button variant="secondary" onClick={() => setReporting(!reporting)}>{reporting ? 'Cancel report' : 'Report'}</Button></div></header>
          <DonationContextCard conversationId={thread.id} />
          <div className={styles.historyAction}><Button variant="secondary" disabled={busy || !messages.length || !hasOlder} onClick={older}>Load older messages</Button></div>
          <div ref={messageArea} className={styles.messages} aria-label="Conversation messages">{!messages.length && <p className={styles.hint}>No messages yet.</p>}{messages.map(m => <div key={m.id} className={Number(m.sender_id) === Number(user.id) ? styles.own : styles.message}>
            {reporting && <label className={styles.messageSelection}><input type="checkbox" aria-label={'Select message from ' + m.sender_name} checked={selected.includes(m.id)} disabled={!selected.includes(m.id) && selected.length >= 10} onChange={e => setSelected(ids => e.target.checked ? [...ids, m.id] : ids.filter(id => id !== m.id))} /> Select message</label>}
            <p className={styles.bubble}>{m.body}</p><small>{formatNotificationTime(m.created_at)}</small>
          </div>)}</div>
          {!reporting && suggestions?.length > 0 && !messages.some(m => Number(m.sender_id) === Number(user.id)) && <div className={styles.suggestions} aria-label="Suggested messages">{suggestions.map(text => <button key={text} type="button" onClick={() => setBody(text)}>{text}</button>)}</div>}
          {reporting ? <form className={styles.reportForm} onSubmit={report}><div className="field"><label htmlFor="report-reason">Report reason</label><input className="chat-control" id="report-reason" required maxLength={500} value={reason} onChange={e => setReason(e.target.value)} /></div><small>{selected.length}/10 selected. Necessary context is shared with the administrator.</small><div><Button type="submit" disabled={busy || !selected.length || selected.length > 10}>Submit report</Button></div></form> : <form className={styles.composer} onSubmit={send}><label className="sr-only" htmlFor="chat-message">Message</label><ChatComposerInput className="chat-control" id="chat-message" placeholder="Type a message..." maxLength={2000} value={body} onChange={e => setBody(e.target.value)} /><Button type="submit" disabled={busy || !body.trim()}>Send</Button></form>}
        </>}
      </div>
    </div>
  </section>
}
