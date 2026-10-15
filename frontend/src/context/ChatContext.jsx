import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react'
import { api } from '../services/apiClient'
import { useAuth } from './AuthContext'
import { useNotify } from './NotificationContext'

const ChatContext = createContext(null)

const MAX_DESKTOP_TABS = 3
const UNREAD_POLL_INTERVAL_MS = 30000

function tabLimit() {
  return Math.max(1, Math.min(MAX_DESKTOP_TABS, Math.floor((window.innerWidth - 32) / 344)))
}

export function ChatProvider({ children }) {
  const { user } = useAuth()
  const notify = useNotify()
  const canChat = user?.account_status === 'active' && user?.verification_status === 'verified' && ['member', 'admin'].includes(user?.role)
  const accountKey = `${user?.id ?? 'guest'}:${canChat}`
  const unreadScope = useRef({ key: accountKey, inFlight: false, queued: false })
  if (unreadScope.current.key !== accountKey) unreadScope.current = { key: accountKey, inFlight: false, queued: false }
  const [unreadCount, setUnreadCount] = useState(0)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [openTabs, setOpenTabs] = useState([])
  const [stateAccountKey, setStateAccountKey] = useState(accountKey)
  const pollTimerRef = useRef(null)

  const fetchUnreadCount = useCallback(async () => {
    const scope = unreadScope.current
    if (scope.key !== accountKey) return
    if (!canChat) {
      setUnreadCount(0)
      return
    }
    if (document.hidden) return
    if (scope.inFlight) { scope.queued = true; return }
    scope.inFlight = true

    try {
      const res = await api.get('/api/chat/unread-count')
      if (unreadScope.current === scope && typeof res?.unread_count === 'number') {
        setUnreadCount(res.unread_count)
      }
    } catch {
      // Background poll failure handled silently
    } finally {
      scope.inFlight = false
      if (unreadScope.current === scope && scope.queued) { scope.queued = false; fetchUnreadCount() }
    }
  }, [accountKey, canChat])

  useEffect(() => {
    setUnreadCount(0)
    setOpenTabs([])
    setDrawerOpen(false)
    setStateAccountKey(accountKey)
  }, [accountKey])

  useEffect(() => {
    if (!canChat) {
      setUnreadCount(0)
      setOpenTabs([])
      setDrawerOpen(false)
      return undefined
    }

    fetchUnreadCount()

    const interval = setInterval(fetchUnreadCount, UNREAD_POLL_INTERVAL_MS)
    pollTimerRef.current = interval

    const onVisibilityChange = () => {
      if (!document.hidden) {
        fetchUnreadCount()
      }
    }
    document.addEventListener('visibilitychange', onVisibilityChange)

    return () => {
      clearInterval(interval)
      document.removeEventListener('visibilitychange', onVisibilityChange)
    }
  }, [user, canChat, fetchUnreadCount])

  useEffect(() => {
    const fitTabs = () => setOpenTabs((tabs) => tabs.length > tabLimit() ? tabs.slice(-tabLimit()) : tabs)
    window.addEventListener('resize', fitTabs)
    return () => window.removeEventListener('resize', fitTabs)
  }, [])

  const openConversationTab = useCallback((conv) => {
    if (unreadScope.current.key !== accountKey) return
    if (!canChat) return
    if (!conv?.id) return

    setOpenTabs((prev) => {
      const existingIndex = prev.findIndex((t) => t.conversationId === conv.id)
      if (existingIndex !== -1) {
        // Unminimize and move to active focus
        const updated = [...prev]
        updated[existingIndex] = {
          ...updated[existingIndex],
          isMinimized: false,
          recipient: conv.recipient || updated[existingIndex].recipient,
          matchRole: conv.match_role || updated[existingIndex].matchRole || null,
          suggestedChats: conv.suggested_chats || updated[existingIndex].suggestedChats || null,
        }
        return updated
      }

      const limit = tabLimit()

      const newTab = {
        conversationId: conv.id,
        recipient: conv.recipient,
        matchRole: conv.match_role || null,
        suggestedChats: conv.suggested_chats || null,
        isMinimized: false,
      }

      if (prev.length >= limit) {
        // Drop the oldest tab
        return [...prev.slice(prev.length - limit + 1), newTab]
      }
      return [...prev, newTab]
    })
  }, [canChat, accountKey])

  const openChatWithUser = useCallback(async (recipientId, fallbackInfo = {}) => {
    const scope = unreadScope.current
    if (scope.key !== accountKey) return
    if (!canChat) {
      notify({ severity: 'warning', message: 'Complete account verification before using chat.', dedupKey: 'chat-verification' })
      return
    }
    if (!recipientId) return

    try {
      const consent = await api.post('/api/chat/requests', { recipient_id: Number(recipientId) })
      if (unreadScope.current !== scope) return
      if (consent.request?.status !== 'accepted') { notify({ severity: 'info', message: 'Message request pending acceptance.', action: { to: '/chat', label: 'View requests' } }); return }
      const res = await api.post('/api/chat/conversations', { recipient_id: Number(recipientId) })
      if (unreadScope.current !== scope) return
      if (res?.conversation) {
        const conv = res.conversation
        if (!conv.recipient && fallbackInfo) {
          conv.recipient = {
            id: recipientId,
            full_name: fallbackInfo.name || fallbackInfo.full_name || 'Member',
            profile_picture_url: fallbackInfo.avatar || fallbackInfo.profile_picture_url || null,
          }
        }
        openConversationTab(conv)
        setDrawerOpen(false)
      }
    } catch (err) {
      if (unreadScope.current !== scope) return
      console.error('Failed to open chat with user:', err)
      notify({ severity: 'error', message: err.message || 'Could not start conversation.' })
    }
  }, [openConversationTab, canChat, notify, accountKey])

  const closeTab = useCallback((conversationId) => {
    setOpenTabs((prev) => prev.filter((t) => t.conversationId !== conversationId))
    fetchUnreadCount()
  }, [fetchUnreadCount])

  const toggleMinimizeTab = useCallback((conversationId) => {
    setOpenTabs((prev) =>
      prev.map((t) => (t.conversationId === conversationId ? { ...t, isMinimized: !t.isMinimized } : t))
    )
  }, [])

  const value = {
    canChat,
    unreadCount: stateAccountKey === accountKey ? unreadCount : 0,
    setUnreadCount,
    fetchUnreadCount,
    drawerOpen: stateAccountKey === accountKey && drawerOpen,
    setDrawerOpen,
    openTabs: stateAccountKey === accountKey ? openTabs : [],
    openConversationTab,
    openChatWithUser,
    closeTab,
    toggleMinimizeTab,
  }

  return <ChatContext.Provider value={value}>{children}</ChatContext.Provider>
}

export function useChat() {
  const context = useContext(ChatContext)
  if (!context) {
    return {
      canChat: false,
      unreadCount: 0,
      setUnreadCount: () => {},
      fetchUnreadCount: () => {},
      drawerOpen: false,
      setDrawerOpen: () => {},
      openTabs: [],
      openConversationTab: () => {},
      openChatWithUser: () => {},
      closeTab: () => {},
      toggleMinimizeTab: () => {},
    }
  }
  return context
}
