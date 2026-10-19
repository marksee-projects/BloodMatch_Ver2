import React, { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { X, Minus, CaretUp, PaperPlaneRight, User, ChatCircleDots } from '@phosphor-icons/react'
import { api } from '../../services/apiClient'
import { useAuth } from '../../context/AuthContext'
import { useChat } from '../../context/ChatContext'
import { useNotify } from '../../context/NotificationContext'
import styles from './FloatingChatTab.module.css'
import ChatComposerInput from './ChatComposerInput'
import DonationContextCard from '../DonationContextCard'

const ACTIVE_POLL_INTERVAL_MS = 3000

function formatTime(isoString) {
  if (!isoString) return ''
  try {
    const d = new Date(isoString.includes('T') ? isoString : `${isoString.replace(' ', 'T')}Z`)
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

export default function FloatingChatTab({ tab, onClose, onToggleMinimize }) {
  const { user } = useAuth()
  const notify = useNotify()
  const { fetchUnreadCount } = useChat()
  const { conversationId, recipient, isMinimized } = tab
  const minimizedRef = useRef(isMinimized)
  minimizedRef.current = isMinimized

  const [isOnline, setIsOnline] = useState(Boolean(recipient?.is_online))
  const [messages, setMessages] = useState([])
  const [suggestedChats, setSuggestedChats] = useState(tab.suggestedChats || null)
  const [suggestedSendingText, setSuggestedSendingText] = useState(null)
  const [inputText, setInputText] = useState('')
  const [sending, setSending] = useState(false)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)

  const messagesEndRef = useRef(null)
  const lastIdRef = useRef(0)
  const pollInFlight = useRef(false)
  const readThroughRef = useRef(0)
  const inputRef = useRef(null)

  const scrollToBottom = useCallback((smooth = true) => {
    messagesEndRef.current?.scrollIntoView({ behavior: smooth && !window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'smooth' : 'auto' })
  }, [])

  const markAsRead = useCallback(async () => {
    try {
      if (document.hidden || minimizedRef.current || lastIdRef.current <= readThroughRef.current) return
      const through = lastIdRef.current
      await api.post(`/api/chat/conversations/${conversationId}/read`, { last_message_id: through })
      readThroughRef.current = Math.max(readThroughRef.current, through)
      fetchUnreadCount()
    } catch {
      // Ignored
    }
  }, [conversationId, fetchUnreadCount])

  // Initial load
  useEffect(() => {
    let mounted = true

    async function loadInitial() {
      try {
        const res = await api.get(`/api/chat/conversations/${conversationId}/messages?limit=50`)
        if (mounted && Array.isArray(res?.messages)) {
          setMessages(res.messages)
          if (res.messages.length > 0) {
            lastIdRef.current = res.messages[res.messages.length - 1].id
          }
          setSuggestedChats(res.suggested_chats || null)
          setIsOnline(Boolean(res.is_online))
          setLoading(false)
          setLoadError(null)
          setTimeout(() => scrollToBottom(false), 50)
          markAsRead()
        }
      } catch (err) {
        if (mounted) {
          setLoading(false)
          setLoadError(err.message || 'Could not load conversation.')
        }
      }
    }

    loadInitial()

    return () => {
      mounted = false
    }
  }, [conversationId, markAsRead, scrollToBottom])

  // Polling for new messages
  useEffect(() => {
    if (isMinimized || loading) return undefined
    let active = true

    const poll = async () => {
      if (document.hidden || pollInFlight.current) return
      pollInFlight.current = true

      try {
        const afterId = lastIdRef.current
        const res = await api.get(
          `/api/chat/conversations/${conversationId}/messages?after_id=${afterId}&limit=50`
        )
        if (!active) return
        setIsOnline(Boolean(res.is_online))
        setSuggestedChats(res.suggested_chats || null)
        if (Array.isArray(res?.messages)) {
          setLoadError(null)
          setLoading(false)
        }
        if (Array.isArray(res?.messages) && res.messages.length > 0) {
          setMessages((prev) => {
            // Deduplicate
            const existingIds = new Set(prev.map((m) => m.id))
            const newMsgs = res.messages.filter((m) => !existingIds.has(m.id))
            if (newMsgs.length === 0) return prev
            return [...prev, ...newMsgs]
          })
          lastIdRef.current = res.messages[res.messages.length - 1].id
          scrollToBottom(true)
          markAsRead()
        }
      } catch {
        // Handled silently
      } finally {
        pollInFlight.current = false
      }
    }

    const timer = setInterval(poll, ACTIVE_POLL_INTERVAL_MS)
    return () => { active = false; clearInterval(timer) }
  }, [conversationId, isMinimized, loading, markAsRead, scrollToBottom])

  const handleSend = async (e) => {
    e.preventDefault()
    const text = inputText.trim()
    if (!text || sending) return

    const tempId = `temp-${Date.now()}`
    const optimisticMsg = {
      id: tempId,
      conversation_id: conversationId,
      sender_id: user?.id,
      sender_name: user?.full_name || 'You',
      body: text,
      created_at: new Date().toISOString(),
      isOptimistic: true,
    }

    setMessages((prev) => [...prev, optimisticMsg])
    setSuggestedChats(null)
    setInputText('')
    setSending(true)
    scrollToBottom(true)

    try {
      const res = await api.post(`/api/chat/conversations/${conversationId}/messages`, { body: text, expected_sender_id: Number(user?.id) })
      if (res?.message) {
        setMessages((prev) =>
          [...prev.filter((m) => m.id !== tempId && m.id !== res.message.id), res.message]
        )
        markAsRead()
      }
    } catch (err) {
      notify({ severity: 'error', message: err.message || 'Failed to send message.' })
      setInputText(text)
      setMessages((prev) => prev.filter((m) => m.id !== tempId))
      setSuggestedChats(suggestedChats)
    } finally {
      setSending(false)
      inputRef.current?.focus()
    }
  }

  const handleSendSuggested = async (text) => {
    const cleanText = text?.trim()
    if (!cleanText || sending || suggestedSendingText) return

    setSuggestedSendingText(cleanText)
    setSending(true)

    const tempId = `temp-${Date.now()}`
    const optimisticMsg = {
      id: tempId,
      conversation_id: conversationId,
      sender_id: user?.id,
      sender_name: user?.full_name || 'You',
      body: cleanText,
      created_at: new Date().toISOString(),
      isOptimistic: true,
    }

    setMessages((prev) => [...prev, optimisticMsg])
    setSuggestedChats(null)
    scrollToBottom(true)

    try {
      const res = await api.post(`/api/chat/conversations/${conversationId}/messages`, {
        body: cleanText,
        expected_sender_id: Number(user?.id),
      })
      if (res?.message) {
        setMessages((prev) =>
          [...prev.filter((m) => m.id !== tempId && m.id !== res.message.id), res.message]
        )
        markAsRead()
      }
    } catch (err) {
      notify({ severity: 'error', message: err.message || 'Failed to send message.' })
      setMessages((prev) => prev.filter((m) => m.id !== tempId))
      setSuggestedChats(tab.suggestedChats || null)
    } finally {
      setSending(false)
      setSuggestedSendingText(null)
      inputRef.current?.focus()
    }
  }

  const recipientName = recipient?.full_name || 'Chat'
  const recipientAvatar = recipient?.profile_picture_url
  const [avatarError, setAvatarError] = useState(false)

  useEffect(() => {
    setAvatarError(false)
  }, [recipientAvatar])

  return (
    <div
      className={`${styles.tabWrapper} ${isMinimized ? styles.tabWrapperMinimized : styles.tabWrapperExpanded}`}
      role="region"
      aria-label={`Chat with ${recipientName}`}
    >
      <header className={styles.tabHeader} onClick={onToggleMinimize}>
        <div className={styles.headerLeft}>
          {recipient?.id && recipient.role === 'member' ? (
            <Link
              to={`/profile/${recipient.id}`}
              className={styles.avatarLink}
              onClick={(e) => e.stopPropagation()}
              title={`View ${recipientName}'s profile`}
              aria-label={`View ${recipientName}'s profile`}
            >
              <div className={styles.avatarWrap}>
                {recipientAvatar && !avatarError ? (
                  <img
                    src={recipientAvatar}
                    alt=""
                    className={styles.avatarImg}
                    onError={() => setAvatarError(true)}
                  />
                ) : (
                  <User size={18} weight="bold" />
                )}
              </div>
            </Link>
          ) : (
            <div className={styles.avatarWrap}>
              {recipientAvatar && !avatarError ? (
                <img
                  src={recipientAvatar}
                  alt=""
                  className={styles.avatarImg}
                  onError={() => setAvatarError(true)}
                />
              ) : (
                <User size={18} weight="bold" />
              )}
            </div>
          )}

          <div className={styles.nameBlock}>
            {recipient?.id && recipient.role === 'member' ? (
              <Link
                to={`/profile/${recipient.id}`}
                className={styles.recipientName}
                onClick={(e) => e.stopPropagation()}
                title={`View ${recipientName}'s profile`}
              >
                {recipientName}
              </Link>
            ) : (
              <span className={styles.recipientName}>{recipientName}</span>
            )}
            <span className={styles.recipientStatus}>{isOnline && <span className={styles.onlineDot} aria-hidden="true" />}{isOnline ? 'Online' : 'Direct conversation'}</span>
          </div>
        </div>

        <div className={styles.headerActions} onClick={(e) => e.stopPropagation()}>
          <button
            type="button"
            className={styles.iconBtn}
            onClick={onToggleMinimize}
            aria-label={isMinimized ? 'Expand chat' : 'Minimize chat'}
            title={isMinimized ? 'Expand chat' : 'Minimize chat'}
          >
            {isMinimized ? <CaretUp size={18} weight="bold" /> : <Minus size={18} weight="bold" />}
          </button>
          <button
            type="button"
            className={styles.iconBtn}
            onClick={onClose}
            aria-label="Close chat"
            title="Close chat"
          >
            <X size={18} weight="bold" />
          </button>
        </div>
      </header>

      {!isMinimized && (
        <div className={styles.bodyArea}>
          <DonationContextCard conversationId={conversationId} />
          <div className={styles.messageList}>
            {loading ? (
              <div className={styles.emptyNotice}>Loading conversation…</div>
            ) : loadError ? (
              <div className={styles.emptyNotice} role="alert">{loadError}</div>
            ) : messages.length === 0 ? (
              <div className={styles.emptyNotice}>
                <ChatCircleDots size={32} className={styles.emptyNoticeIcon} />
                <span>No messages yet. Say hello to {recipientName}!</span>
              </div>
            ) : (
              messages.map((m) => {
                const isSentByMe = Number(m.sender_id) === Number(user?.id)
                return (
                  <div
                    key={m.id}
                    className={`${styles.messageRow} ${
                      isSentByMe ? styles.messageRowSent : styles.messageRowReceived
                    }`}
                  >
                    <div
                      className={`${styles.bubble} ${
                        isSentByMe ? styles.bubbleSent : styles.bubbleReceived
                      } ${m.isOptimistic ? styles.bubbleOptimistic : ''}`}
                    >
                      {m.body}
                    </div>
                    <span className={styles.msgTime}>
                      {m.isOptimistic ? 'Sending…' : formatTime(m.created_at)}
                    </span>
                  </div>
                )
              })
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Suggested Chats (Quick Replies) for Matched Users */}
          {!loading && !messages.some(m => Number(m.sender_id) === Number(user?.id)) && suggestedChats && suggestedChats.length > 0 && (
            <div className={styles.suggestedContainer} role="region" aria-label="Suggested messages">
              <div className={styles.suggestedHeader}>
                <span>Suggested messages</span>
              </div>
              <div className={styles.suggestedList}>
                {suggestedChats.map((text, idx) => (
                  <button
                    key={idx}
                    type="button"
                    className={styles.suggestedPill}
                    onClick={() => handleSendSuggested(text)}
                    disabled={sending || suggestedSendingText !== null}
                    aria-label={`Send suggested message: "${text}"`}
                    title={`Send: "${text}"`}
                  >
                    {suggestedSendingText === text ? (
                      <span className={styles.suggestedSendingText}>Sending…</span>
                    ) : (
                      <span>{text}</span>
                    )}
                  </button>
                ))}
              </div>
            </div>
          )}

          <form className={styles.inputForm} onSubmit={handleSend}>
            <ChatComposerInput
              inputRef={inputRef}
              className={`chat-control ${styles.chatInput}`}
              placeholder="Type a message…"
              value={inputText}
              onChange={(e) => setInputText(e.target.value)}
              aria-label="Type a message"
              maxLength={2000}
            />
            <button
              type="submit"
              className={styles.sendBtn}
              disabled={!inputText.trim() || sending}
              aria-label="Send message"
              title="Send message"
            >
              <PaperPlaneRight size={22} weight="fill" />
            </button>
          </form>
        </div>
      )}
    </div>
  )
}
