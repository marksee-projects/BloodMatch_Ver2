import { Fragment, createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api, clearCsrf } from '../services/apiClient'

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
  const applySessionUser = useCallback((nextUser) => {
    const nextId = nextUser?.id ?? null
    if (userIdRef.current !== nextId) {
      // clear() also cancels old queries so late results cannot restore them.
      queryClient.clear()
      userIdRef.current = nextId
    }
    setUser(nextUser)
  }, [queryClient])

  const refresh = async () => {
    const authVersion = authVersionRef.current
    try {
      const data = await api.get('/api/auth/me')
      if (authVersion === authVersionRef.current) applySessionUser(data.user)
    } catch {
      if (authVersion === authVersionRef.current) applySessionUser(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    refresh()
  }, [])

  const login = async (email, password) => {
    const authVersion = ++authVersionRef.current
    const data = await api.post('/api/login', { email, password })
    if (authVersion === authVersionRef.current) applySessionUser(data.user)
    return data.user
  }

  const logout = async () => {
    const authVersion = ++authVersionRef.current
    try {
      await api.post('/api/logout')
    } finally {
      if (authVersion === authVersionRef.current) {
        applySessionUser(null)
        clearCsrf()
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
