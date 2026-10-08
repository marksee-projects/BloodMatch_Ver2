import { Fragment, createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api, clearCsrf } from '../services/apiClient'

const SESSION_CHANGE_KEY = 'bloodmatch-session-change'

function announceSessionChange() {
  try {
    localStorage.setItem(SESSION_CHANGE_KEY, `${Date.now()}-${Math.random()}`)
  } catch {
    // Focus revalidation still works when browser storage is unavailable.
  }
}

const AuthContext = createContext({
  user: null,
  loading: true,
  login: async () => {},
  logout: async () => {},
  refresh: async () => {}
})

export function AuthProvider({ children }) {
  const queryClient = useQueryClient()
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  const userIdRef = useRef(null)
  const authVersionRef = useRef(0)
  const authMutationRef = useRef(false)
  const applySessionUser = useCallback((nextUser) => {
    const nextId = nextUser?.id ?? null
    if (userIdRef.current !== nextId) {
      // clear() also cancels old queries so late results cannot restore them.
      queryClient.clear()
      userIdRef.current = nextId
    }
    setUser(nextUser)
  }, [queryClient])

  const refresh = useCallback(async () => {
    if (authMutationRef.current) return
    const authVersion = ++authVersionRef.current
    try {
      const data = await api.get('/api/auth/me')
      if (authVersion === authVersionRef.current) applySessionUser(data.user)
    } catch {
      if (authVersion === authVersionRef.current) applySessionUser(null)
    } finally {
      if (authVersion === authVersionRef.current) setLoading(false)
    }
  }, [applySessionUser])

  useEffect(() => {
    refresh()
    const revalidate = () => {
      if (document.visibilityState === 'visible') refresh()
    }
    const onStorage = (event) => {
      if (event.key !== SESSION_CHANGE_KEY) return
      ++authVersionRef.current
      clearCsrf()
      applySessionUser(null)
      setLoading(true)
      refresh()
    }
    window.addEventListener('storage', onStorage)
    window.addEventListener('focus', revalidate)
    document.addEventListener('visibilitychange', revalidate)
    return () => {
      window.removeEventListener('storage', onStorage)
      window.removeEventListener('focus', revalidate)
      document.removeEventListener('visibilitychange', revalidate)
    }
  }, [refresh, applySessionUser])

  const login = async (email, password) => {
    const authVersion = ++authVersionRef.current
    authMutationRef.current = true
    let data
    try {
      data = await api.post('/api/login', { email, password })
    } finally {
      authMutationRef.current = false
    }
    if (authVersion === authVersionRef.current) {
      applySessionUser(data.user)
      announceSessionChange()
    }
    return data.user
  }

  const logout = async () => {
    const authVersion = ++authVersionRef.current
    authMutationRef.current = true
    try {
      await api.post('/api/logout')
    } finally {
      authMutationRef.current = false
      if (authVersion === authVersionRef.current) {
        applySessionUser(null)
        clearCsrf()
        announceSessionChange()
      }
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, refresh }}>
      <Fragment key={user?.id ?? 'signed-out'}>{children}</Fragment>
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
