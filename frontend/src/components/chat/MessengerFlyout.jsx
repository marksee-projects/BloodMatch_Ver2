import { Link } from 'react-router-dom'
import React, { useState, useEffect, useRef, useCallback } from 'react'
import { ChatCircleDots, MagnifyingGlass, User } from '@phosphor-icons/react'
import { api } from '../../services/apiClient'
import { useChat } from '../../context/ChatContext'
import { useAuth } from '../../context/AuthContext'
import { useActionNoticeState } from '../../context/NotificationContext'
import styles from './MessengerFlyout.module.css'

function formatSnippetTime(isoString) {
  if (!isoString) return ''
  try {
    const d = new Date(isoString.includes('T') ? isoString : `${isoString.replace(' ', 'T')}Z`)
    const now = new Date()
    const diffSec = Math.floor((now.getTime() - d.getTime()) / 1000)

    if (diffSec < 60) return 'Just now'
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m`
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h`
    if (diffSec < 172800) return 'Yesterday'
    return d.toLocaleDateString('en-US', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric' })
  } catch {
    return ''
  }
}

function ConvAvatar({ src, name }) {
  const [failed, setFailed] = useState(false)
  useEffect(() => {
    setFailed(false)
  }, [src])

  if (!src || failed) {
    return <User size={22} weight="bold" />
  }

  return (
    <img
      src={src}
      alt={name ? `${name}'s profile picture` : ''}
      className={styles.convAvatar}
      onError={() => setFailed(true)}
    />
  )
}

export default function MessengerFlyout({ variant = 'desktop' }) {
  const { unreadCount, openConversationTab, canChat, fetchUnreadCount } = useChat()
  const { user } = useAuth()
  const [open, setOpen] = useState(false)
  const [conversations, setConversations] = useState([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useActionNoticeState(null, 'error')
  const rootRef = useRef(null)
  const mounted = useRef(false)
  const inFlight = useRef(false)
  const openRef = useRef(open)
  openRef.current = open
  useEffect(() => { mounted.current = true; return () => { mounted.current = false } }, [])

  const loadConversations = useCallback(async ({ acknowledge = false } = {}) => {
    if (inFlight.current || document.hidden) return
    inFlight.current = true
    setLoading(true)
    setError(null)
    try {
      const res = await api.get('/api/chat/conversations')
      if (!mounted.current) return
      if (Array.isArray(res?.conversations)) {
        setConversations(res.conversations)
        if (acknowledge && openRef.current && !document.hidden) {
          // Acknowledge only the message boundary shown in these previews.
          // A message arriving after this response must remain unread.
          const unread = res.conversations.filter(conv => conv.unread_count > 0 && Number(conv.last_message?.id) > 0)
          const results = await Promise.allSettled(unread.map(conv =>
            api.post(`/api/chat/conversations/${conv.id}/read`, { last_message_id: Number(conv.last_message.id) })
          ))
          const readIds = new Set(unread.filter((_, index) => results[index].status === 'fulfilled').map(conv => conv.id))
          if (mounted.current) {
            setConversations(current => current.map(conv => readIds.has(conv.id) ? { ...conv, unread_count: 0 } : conv))
            if (results.some(result => result.status === 'rejected')) setError('Some chats could not be marked as read. Reopen the menu to retry.')
          }
          await fetchUnreadCount()
        }
      }
    } catch (err) {
      if (mounted.current) setError(err.message || 'Could not load chats.')
    } finally {
      inFlight.current = false
      if (mounted.current) setLoading(false)
    }
  }, [fetchUnreadCount])

  useEffect(() => {
    if (open && canChat) {
      loadConversations({ acknowledge: true })
      fetchUnreadCount()
      const timer = window.setInterval(() => { if (!document.hidden) { loadConversations(); fetchUnreadCount() } }, 10000)
      return () => window.clearInterval(timer)
    }
  }, [open, canChat, loadConversations, fetchUnreadCount])

  // Handle outside click & escape key
  useEffect(() => {
    if (!open) return undefined

    const onPointerDown = (e) => {
      if (rootRef.current && !rootRef.current.contains(e.target)) {
        setOpen(false)
      }
    }
    const onKeyDown = (e) => {
      if (e.key === 'Escape') {
        setOpen(false)
      }
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  const filtered = conversations.filter((c) => {
    if (!search.trim()) return true
    const name = c.recipient?.full_name?.toLowerCase() || ''
    return name.includes(search.toLowerCase().trim())
  })

  const handleSelect = (conv) => {
    openConversationTab(conv)
    setOpen(false)
  }

  const badgeText = unreadCount > 0 ? (unreadCount > 99 ? '99+' : String(unreadCount)) : null
  if (!canChat) return null

  return (
    <div ref={rootRef} className={styles.wrap}>
      <button
        type="button"
        className={`nav-control ${styles.triggerBtn}`}
        onClick={() => setOpen((prev) => !prev)}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={`Chats${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
        title="Chats"
      >
        <ChatCircleDots size={variant === 'mobile' ? 24 : 26} weight="regular" aria-hidden="true" />
        {!open && badgeText && <span className={styles.badge}>{badgeText}</span>}
      </button>

      {open && (
        <div className={styles.popover} role="dialog" aria-label="Chats menu">
          <div className={styles.header}>
            <div className={styles.headerTop}>
              <h3 className={styles.title}>Chats</h3><Link to="/chat" onClick={() => setOpen(false)}>Open full chat</Link>
            </div>
            <div className={styles.searchWrap}>
              <MagnifyingGlass size={16} className={styles.searchIcon} aria-hidden="true" />
              <input
                type="text"
                className={`chat-control ${styles.searchInput}`}
                placeholder="Search chats…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                aria-label="Search Chats"
              />
            </div>
          </div>

          <div className={styles.listArea}>
            {loading ? (
              <div className={styles.emptyState}>
                <p>Loading chats…</p>
              </div>
            ) : error ? (
              <div className={styles.emptyState} role="alert">
                <p>{error}</p>
                <button type="button" className="btn btn-secondary" onClick={loadConversations}>Try again</button>
              </div>
            ) : filtered.length === 0 ? (
              <div className={styles.emptyState}>
                <h4>No chats found</h4>
                <p>
                  {search
                    ? 'No conversations matched your search.'
                    : 'Start a conversation with a donor or requester.'}
                </p>
              </div>
            ) : (
              filtered.map((conv) => {
                const isUnread = conv.unread_count > 0
                const name = conv.recipient?.full_name || 'Member'
                const avatar = conv.recipient?.profile_picture_url
                const lastMsg = conv.last_message

                return (
                  <button
                    key={conv.id}
                    type="button"
                    className={`${styles.convItem} ${isUnread ? styles.convItemUnread : ''}`}
                    onClick={() => handleSelect(conv)}
                  >
                    <div className={styles.convAvatarWrap}>
                      <ConvAvatar src={avatar} name={name} />{conv.recipient?.is_online && <span className={styles.onlineDot} role="img" aria-label="Online" />}
                    </div>
                    <div className={styles.convContent}>
                      <div className={styles.convTopLine}>
                        <span className={styles.convName}>{name}</span>
                        {lastMsg && (
                          <span className={styles.convTime}>
                            {formatSnippetTime(lastMsg.created_at)}
                          </span>
                        )}
                      </div>
                      <div className={styles.convBottomLine}>
                        <span
                          className={`${styles.convSnippet} ${
                            isUnread ? styles.convSnippetUnread : ''
                          }`}
                        >
                          {lastMsg ? (Number(lastMsg.sender_id) === Number(user?.id) ? 'You: ' : '') + lastMsg.body : 'Conversation started'}
                        </span>
                        {isUnread && <span className={styles.unreadDot} aria-hidden="true" />}
                      </div>
                    </div>
                  </button>
                )
              })
            )}
          </div>
        </div>
      )}
    </div>
  )
}
